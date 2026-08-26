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

Old schema migration files are archived rather than discarded.

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

Migraw:

1. verifies that schema migrations are not pending;
2. reads the current database schema;
3. creates a new baseline migration;
4. archives previous schema migrations;
5. retimestamps population migrations;
6. replaces the migration repository history with the baseline;
7. stores a recovery manifest in the squash archive.

The existing database schema is not recreated during squash.

## New installations

After squash, a new installation starts from the baseline instead of replaying the entire historical migration chain.

```text
schema baseline
      ↓
population migrations
      ↓
new migrations
```

## Existing installations

Existing databases are not supposed to execute the baseline over tables that already exist.

Migraw replaces the managed migration history so the existing schema is represented by the new baseline.

## Archive

A squash archive contains the previous schema migrations and a `manifest.json` recovery snapshot.

Example:

```text
database/migrations/
├── 2026_08_26_010000_schema.php
├── 2026_08_26_010001_populate_roles.php
└── archive/
    └── 20260826_010000/
        ├── manifest.json
        ├── 2026_06_01_create_users.php
        └── ...
```

## Unsquash

Unsquash restores the migration history represented by a squash archive.

```bash
php vendor/bin/migraw unsquash
```

To select a specific archive:

```bash
php vendor/bin/migraw unsquash 20260826_010000
```

Unsquash restores:

- archived schema migration files;
- original population migration filenames;
- migration batches;
- checksums;
- execution history.

It removes the squash baseline from the active migration files.

## Unsquash is not rollback

`rollback` changes the database schema by executing migration `down()` operations.

`unsquash` changes the migration history representation while leaving the database schema unchanged.

Migraw refuses unsafe unsquash operations when the current migration history no longer matches the squash state.
