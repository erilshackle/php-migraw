# Squash & Unsquash

## What squash does

Squash replaces a long migration history with a new schema baseline representing the current database schema.

For example:

```text
001_create_users
002_add_phone
003_create_roles
004_create_consultas
005_alter_consultas
```

becomes approximately:

```text
006_schema
```

Old schema migrations are archived rather than discarded.

Population migrations are preserved and retimestamped after the new baseline.

## Run squash

```bash
php vendor/bin/migraw squash
```

You can provide a baseline name:

```bash
php vendor/bin/migraw squash app_schema
```

Use `--force` to skip confirmation:

```bash
php vendor/bin/migraw squash app_schema --force
```

## What happens

When squash runs, Migraw:

1. verifies that schema migrations are not pending;
2. reads the current database schema;
3. creates a new baseline migration;
4. archives superseded schema migrations;
5. retimestamps preserved population migrations;
6. replaces the migration repository history with the new baseline and executed population migrations;
7. stores the previous migration history and recovery information in `manifest.json`.

The existing database schema and application data are not recreated during squash.

## New installations

After squash, a new installation starts from the baseline instead of replaying the entire historical migration chain.

```text
schema baseline
      ↓
population migrations
      ↓
new migrations
```

For a new database, the baseline is a normal pending migration and its `up()` operation builds the schema.

## Existing installations

Existing databases must not execute the baseline over tables that already exist.

Starting with Migraw 1.5, the normal `migrate` command automatically reconciles existing migration history with committed squash checkpoints.

```bash
php vendor/bin/migraw migrate
```

Migraw determines the appropriate migration path from the existing repository history.

### Already at the pre-squash state

If the database has executed the complete migration history represented by the squash:

```text
001
002
003
      ↓
006_schema
```

Migraw adopts `006_schema` as the new representation of that history.

The baseline `up()` operation is not executed.

### Behind the squash checkpoint

An existing installation may not have executed every migration that existed when the squash was created.

For example:

```text
Squash history:
001
002
003
004
      ↓
006_schema

Existing database:
001
002
```

Migraw detects the missing migrations and executes them from the committed squash archive:

```text
001
002
 ↓
003  ← archive
004  ← archive
 ↓
006_schema adopted
```

After catch-up completes, the old repository history is replaced by the baseline representation.

The baseline itself is not executed against the existing schema.

### After adoption

Any migrations created after the baseline continue normally:

```text
old history
     ↓
catch-up if required
     ↓
baseline adoption
     ↓
new migrations
```

For example:

```text
001
002
003
      ↓
006_schema
007_add_timezone
008_create_settings
```

An existing installation can safely run:

```bash
php vendor/bin/migraw migrate
```

Migraw reconciles the squash checkpoint first and then executes `007_add_timezone`, `008_create_settings`, and any other pending migrations.

## Pretend mode

Use `--pretend` to preview the complete migration path without modifying the database or migration repository:

```bash
php vendor/bin/migraw migrate --pretend
```

Pretend mode also understands squash checkpoints.

If an existing database is behind a squash, the output includes SQL for:

* missing archived migrations required for catch-up;
* pending population migrations when applicable;
* migrations created after the squash.

It does not execute the baseline SQL when the baseline would only be adopted.

For example:

```text
existing database:
001

squash checkpoint:
001
002
003
    ↓
006_schema

new migration:
007_add_timezone
```

The pretend migration path is effectively:

```text
002
003
007_add_timezone
```

The database and migration repository remain unchanged.

## Diverged histories

Squash adoption is only performed when the existing migration history can be safely reconciled with the history stored in the squash manifest.

Migraw refuses adoption when it detects conditions such as:

* unexpected migrations in the existing history;
* incompatible migration ordering;
* checksum mismatches;
* modified archived migrations;
* modified preserved population migrations.

Migraw does not silently force a database into a squash checkpoint when its history cannot be verified.

Resolve the migration history conflict before running `migrate` again.

## Population migrations

Population migrations are not merged into the schema baseline.

During squash they are preserved and retimestamped so they remain ordered after the generated baseline.

For example:

```text
001_create_roles
002_create_users
003_populate_roles
```

may become:

```text
004_schema
005_populate_roles
```

If `003_populate_roles` had already been executed, its executed state is preserved when the baseline is adopted.

If it was pending, it remains pending and can execute normally after the baseline.

Migraw also tracks population migration renames across later squash checkpoints so existing installations can be reconciled through multiple squashes.

## Multiple squashes

A project may squash its migration history more than once.

For example:

```text
001
002
003
    ↓
100_schema

101_add_profile
102_add_settings
    ↓
200_schema

201_add_timezone
```

Each completed squash archive acts as a migration history checkpoint.

When necessary, Migraw can reconcile an existing installation through the committed checkpoints in order:

```text
old installation
      ↓
first checkpoint
      ↓
second checkpoint
      ↓
current migrations
```

This allows long-lived installations to catch up even when multiple squashes have occurred since their last deployment.

## Archive

A squash archive contains superseded schema migrations and a `manifest.json` snapshot.

Example:

```text
database/migrations/
├── 2026_08_29_010000_schema.php
├── 2026_08_29_010001_populate_roles.php
├── 2026_08_29_020000_add_timezone.php
└── archive/
    └── 20260829_010000/
        ├── manifest.json
        ├── 2026_06_01_create_users.php
        ├── 2026_06_02_create_roles.php
        └── ...
```

The archive is not disposable backup data.

It is part of the managed migration history and may be required to bring an existing installation forward after a squash.

Commit the generated baseline, retimestamped population migrations, `archive/` directory, and `manifest.json` together to version control.

Do not remove old squash archives while installations may still depend on them for migration catch-up or squash recovery.

## manifest.json

Each squash archive contains a `manifest.json` describing the checkpoint.

It records information required for reconciliation and recovery, including:

* the generated baseline;
* the migration repository history before squash;
* archived schema migrations;
* preserved and retimestamped population migrations;
* migration checksums;
* squash status and timestamps.

Migraw uses completed manifests when reconciling existing installations during `migrate`.

The manifest should be committed together with its archive and should not be edited manually.

## Deployment workflow

A typical squash deployment is:

```text
Development
    │
    ├── run all pending migrations
    │
    ├── squash
    │
    ├── commit baseline
    ├── commit preserved populations
    ├── commit archive + manifest
    │
    └── deploy
            │
            ▼
        Production
            │
            └── migraw migrate
                    │
                    ├── fresh database
                    │      └── execute baseline
                    │
                    ├── current old database
                    │      └── adopt baseline
                    │
                    ├── behind database
                    │      └── catch up from archive
                    │          → adopt baseline
                    │
                    └── diverged database
                           └── abort
```

No special production squash command is required.

The deployed application continues to use:

```bash
php vendor/bin/migraw migrate
```

## Unsquash

Unsquash restores the migration history represented by a squash archive.

```bash
php vendor/bin/migraw unsquash
```

To select a specific archive:

```bash
php vendor/bin/migraw unsquash 20260829_010000
```

Unsquash restores:

* archived schema migration files;
* original population migration filenames;
* migration batches;
* checksums;
* execution history.

It removes the corresponding squash baseline from the active migration files.

## Unsquash is not rollback

`rollback` changes the database schema by executing migration `down()` operations.

`unsquash` changes how the existing database state is represented by migration history.

It does not revert the current database schema or application data.

Migraw refuses unsafe unsquash operations when the current migration history no longer matches the squash state.
