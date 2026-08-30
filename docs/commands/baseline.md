# Baseline

The `baseline` command lets you start using Migraw in an existing project that already has a database schema but no Migraw migration history.

Instead of recreating the project's historical migrations, Migraw captures the current schema and uses it as the starting point for future migrations.

## Create a baseline

```bash
php vendor/bin/migraw baseline
```

This generates a migration named similar to:

```text
2026_08_30_120000_baseline.php
```

You can provide a custom name:

```bash
php vendor/bin/migraw baseline legacy_schema
```

which generates something similar to:

```text
2026_08_30_120000_legacy_schema.php
```

## What happens

When a baseline is created, Migraw:

1. Reads the current database schema.
2. Generates a normal migration capable of recreating that schema.
3. Calculates the migration checksum.
4. Registers the baseline in the migration repository as already executed.

The existing application schema and data are not modified.

Conceptually:

```text
Existing project

Database
├── users
├── roles
├── posts
└── ...

Migraw history
└── empty

        │
        │ migraw baseline
        ▼

database/migrations/
└── 2026_08_30_120000_baseline.php

Migraw history
└── 2026_08_30_120000_baseline [ran]

Database
├── users
├── roles
├── posts
└── ...

        unchanged
```

Migraw does **not** execute the generated baseline against the existing database.

## Continue with normal migrations

After creating the baseline, use Migraw normally.

For example:

```bash
php vendor/bin/migraw make add_timezone_to_users
php vendor/bin/migraw migrate
```

The migration history then becomes:

```text
2026_08_30_120000_baseline
2026_08_30_130000_add_timezone_to_users
```

Only the new migration is executed against the existing database.

## Fresh installations

A baseline is a normal Migraw migration.

This means the same migration can be used to create the initial schema on a fresh database.

For example, after cloning the project:

```bash
php vendor/bin/migraw migrate
```

Migraw executes:

```text
baseline
    ↓
later migrations
```

The baseline recreates the schema that existed when it was generated, and subsequent migrations bring the database up to the current state.

This makes the baseline both:

* the adoption point for the existing database;
* the starting schema for future installations.

## Safety checks

Baseline creation is intentionally strict.

Migraw refuses to create a baseline when migration history already exists.

In that situation, the project is already managed by Migraw and `squash` should normally be used instead.

Migraw also refuses baseline creation when migration files already exist in the configured migration directory.

This prevents existing migration files from unexpectedly becoming pending migrations after the baseline is created.

Finally, Migraw requires an application schema to capture.

If no application tables are found, no baseline is created.

## Existing data

A baseline captures the database **schema**, not the existing application data.

For example, if the existing database contains:

```text
roles

1  admin  Administrator
2  user   User
```

the baseline recreates the `roles` table structure, but does not automatically include those records.

Data required by new installations should be represented explicitly using population migrations.

For example:

```bash
php vendor/bin/migraw make populate_roles --populate
```

Population migrations should be idempotent so they can safely reconcile data that may already exist in the original database.

## Baseline vs squash

`baseline` and `squash` both generate schema migrations, but they solve different problems.

### Baseline

Use `baseline` when:

```text
database schema exists
+
Migraw history does not exist
```

It establishes the first Migraw migration for an existing project.

### Squash

Use `squash` when:

```text
database schema exists
+
Migraw migration history already exists
```

It consolidates an existing migration history into a new schema baseline while preserving the information required for existing installations to reconcile the squash.

In short:

```text
Existing project adopting Migraw
→ baseline

Existing Migraw project consolidating history
→ squash
```

## Deployment

After creating a baseline, commit the generated migration to version control:

```bash
git add database/migrations
git commit -m "Add initial Migraw baseline"
```

From that point forward, the baseline is part of the project's migration history and should be deployed with the application like any other migration.

Existing database:

```text
baseline already marked as ran
        ↓
migraw migrate
        ↓
only later migrations execute
```

Fresh database:

```text
empty migration history
        ↓
migraw migrate
        ↓
baseline executes
        ↓
later migrations execute
```

## Summary

Use:

```bash
php vendor/bin/migraw baseline
```

when introducing Migraw to an existing project.

The command captures the current schema, creates a reproducible migration, and marks it as already executed without modifying the existing application schema or data.

After that, the project can use normal Migraw migrations going forward.
