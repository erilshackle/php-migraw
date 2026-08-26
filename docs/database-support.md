# Database Support

Migraw supports PDO connections for:

| Database | Driver |
| --- | --- |
| MySQL | `mysql` |
| MariaDB | `mysql` |
| PostgreSQL | `pgsql` |
| SQLite | `sqlite` |

## Feature support

| Feature | MySQL / MariaDB | PostgreSQL | SQLite |
| --- | :---: | :---: | :---: |
| Raw migrations | ✓ | ✓ | ✓ |
| Fluent migrations | ✓ | ✓ | ✓ |
| Population migrations | ✓ | ✓ | ✓ |
| Dry run | ✓ | ✓ | ✓ |
| Checksums / status / repair | ✓ | ✓ | ✓ |
| Schema squash | ✓ | Planned | Planned |

Schema squash currently targets MySQL/MariaDB. PostgreSQL and SQLite squash support can be added independently because schema dumping differs between database engines.

## PDO drivers

Typical PHP extensions:

```text
pdo_mysql
pdo_pgsql
pdo_sqlite
```

## Portable migrations

When a project must run on multiple database engines, prefer SQL and fluent operations supported by all target engines.

For projects tied to one database engine, database-specific raw SQL is fully supported.
