# Commands

## Quick reference

| Command | Arguments | Options | Purpose |
| --- | --- | --- | --- |
| `init` | — | `--force` | Generate default config |
| `init:mysql` | — | `--force` | Generate MySQL defaults |
| `init:pgsql` | — | `--force` | Generate PostgreSQL defaults |
| `init:sqlite` | — | `--force` | Generate SQLite defaults |
| `make` | `<name>` | `--sql`, `--populate` | Create a migration |
| `migrate` / `up` | — | `--dry-run`, `--pretend` | Run pending migrations |
| `rollback` / `down` | — | `--dry-run`, `--pretend` | Roll back last batch |
| `reset` | — | `--dry-run`, `--pretend` | Roll back all migrations |
| `refresh` | — | `--dry-run`, `--pretend` | Roll back and rerun |
| `fresh` | — | — | Rebuild from a clean schema |
| `status` | — | — | Show migration status |
| `validate` | — | — | Validate migration files |
| `doctor` | — | — | Check environment/config |
| `repair` | — | `--modified`, `--force` | Repair repository records |
| `squash` | `[name]` | `--force` | Create a schema baseline |
| `unsquash` | `[archive]` | `--force` | Restore pre-squash history |
| `help` | — | — | Show CLI help |

## `init`

```bash
php vendor/bin/migraw init
```

Driver-specific defaults:

```bash
php vendor/bin/migraw init:mysql
php vendor/bin/migraw init:pgsql
php vendor/bin/migraw init:sqlite
```

Overwrite an existing generated config:

```bash
php vendor/bin/migraw init:mysql --force
```

## `make`

```bash
php vendor/bin/migraw make create_users_table
```

### Argument

| Argument | Required | Description |
| --- | --- | --- |
| `name` | Yes | Migration name used for template detection and filename |

### Options

| Option | Description |
| --- | --- |
| `--sql` | Force a blank raw SQL migration |
| `--populate` | Create a `PopulatorMigration` |

Examples:

```bash
php vendor/bin/migraw make add_phone_to_users
```

```bash
php vendor/bin/migraw make custom_database_change --sql
```

```bash
php vendor/bin/migraw make populate_roles --populate
```

`--sql` and `--populate` cannot be used together.

## `migrate` / `up`

Run pending migrations:

```bash
php vendor/bin/migraw migrate
```

Alias:

```bash
php vendor/bin/migraw up
```

Preview without executing:

```bash
php vendor/bin/migraw migrate --dry-run
```

or:

```bash
php vendor/bin/migraw migrate --pretend
```

## `rollback` / `down`

Roll back the latest batch:

```bash
php vendor/bin/migraw rollback
```

Alias:

```bash
php vendor/bin/migraw down
```

Preview:

```bash
php vendor/bin/migraw rollback --dry-run
```

## `reset`

Roll back all managed migrations:

```bash
php vendor/bin/migraw reset
```

Preview:

```bash
php vendor/bin/migraw reset --pretend
```

## `refresh`

Roll back all managed migrations and execute them again:

```bash
php vendor/bin/migraw refresh
```

Preview:

```bash
php vendor/bin/migraw refresh --dry-run
```

## `fresh`

Rebuild from a clean schema:

```bash
php vendor/bin/migraw fresh
```

Use this when you intentionally want a clean database rebuilt from the active migrations.

## `status`

```bash
php vendor/bin/migraw status
```

Typical states include:

```text
[ran]
[pending]
[ran modified]
[missing]
```

## `validate`

```bash
php vendor/bin/migraw validate
```

Checks migration files and verifies that migration methods return supported statement types.

## `doctor`

```bash
php vendor/bin/migraw doctor
```

Checks common setup problems such as:

- config file
- configured bootstrap
- database connection
- PDO driver
- migrations path
- migration repository

## `repair`

Remove migration records whose files are missing:

```bash
php vendor/bin/migraw repair
```

Accept current checksums of intentionally modified migrations:

```bash
php vendor/bin/migraw repair --modified
```

Skip confirmation:

```bash
php vendor/bin/migraw repair --modified --force
```

`repair --modified` updates the repository checksum. It does not rerun the migration.

## `squash`

```bash
php vendor/bin/migraw squash
```

Custom baseline name:

```bash
php vendor/bin/migraw squash app_schema
```

Skip confirmation:

```bash
php vendor/bin/migraw squash app_schema --force
```

See [Squash & Unsquash](squash.md).

## `unsquash`

Restore the latest squash history:

```bash
php vendor/bin/migraw unsquash
```

Select an archive:

```bash
php vendor/bin/migraw unsquash 20260826_010000
```

Skip confirmation:

```bash
php vendor/bin/migraw unsquash 20260826_010000 --force
```

Unsquash restores migration history and files. It does not roll back the current database schema.

## `help`

```bash
php vendor/bin/migraw help
```
