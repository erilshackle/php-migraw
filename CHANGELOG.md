# Changelog

*Changelog created using the [Simple Changelog](https://marketplace.visualstudio.com/items?itemName=tobiaswaelde.vscode-simple-changelog) extension for VS Code.*

## [1.5.0] - 2026-08-29

### Added

* Added PostgreSQL schema squash support.
* Added SQLite schema squash support.
* Added driver-specific schema dumpers for MySQL, PostgreSQL and SQLite.
* Added automatic squash adoption during normal `migrate` operations.
* Added automatic catch-up for databases that are behind a committed squash checkpoint.
* Added support for executing missing pre-squash migrations directly from committed squash archives.
* Added migration history reconciliation through completed squash manifests.
* Added support for sequential squash adoption across multiple squash checkpoints.
* Added population migration resolution across multiple squash retimestamps.
* Added dry-run support for squash reconciliation with `--pretend`.
* Added checksum validation for archived migrations and preserved population migrations during squash adoption.
* Added integration tests covering squash adoption, production catch-up and pretend reconciliation.

### Changed

* `migrate` now reconciles existing migration history with available squash checkpoints before resolving pending migrations.
* Squash archives can now be committed and used as migration history checkpoints for existing installations.
* Existing databases at the pre-squash state now adopt the generated baseline without executing its schema SQL.
* Databases behind a squash checkpoint now execute only the missing archived migrations before adopting the baseline.
* Preserved population migrations now remain correctly represented when a squash baseline is adopted.
* Pretend mode now simulates the post-reconciliation migration state without modifying the database or migration repository.
* Migration repository history remains the source of truth after squash reconciliation.

### Fixed

* Fixed deployment of squashed migration histories to existing databases that had not yet adopted the generated baseline.
* Fixed squashed deployments attempting to execute baseline schema SQL against already-existing database structures.
* Fixed databases behind the pre-squash migration history being unable to safely migrate after archived migrations were replaced by a baseline.
* Fixed pretend mode incorrectly treating an adopted squash baseline as a pending migration.
* Fixed preserved population migrations potentially being executed again during pretend reconciliation.
* Fixed squash adoption across multiple checkpoints with repeatedly retimestamped population migrations.


## [1.4.0] - 2026-08-26

### Added

* Added the `unsquash` command for restoring migration history from a previous squash.
* Added squash recovery through `manifest.json`.
* Added migration repository snapshots for squash and unsquash operations.
* Added restoration of archived schema migrations during unsquash.
* Added restoration of retimestamped population migrations during unsquash.
* Added recovery of migration batches, checksums and execution history.
* Added MkDocs documentation with guides for configuration, migrations, commands, population, squash and database support.
* Added dedicated `llms.txt` documentation index and `llms-full.txt` reference for AI coding assistants.

### Changed

* Refactored the CLI application into `Migraw`, `RuntimeContext` and `CommandHandler`.
* Renamed the main `Application` entry point to `Migraw`.
* Improved squash safety with recoverable filesystem and migration repository state.
* Improved squash archives to preserve enough metadata for reversing a squash.
* Improved documentation for raw and fluent migrations, command arguments and CLI options.
* Reduced the README to focus on installation, quick start, core features and documentation links.

### Fixed

* Fixed squash recovery when an operation fails after migration files have been archived.
* Fixed migration repository recovery when squash fails after replacing migration history.
* Fixed squash archive handling so a single archive path is used throughout the operation.
* Fixed population migration restoration when reversing a squash.

## [1.3.1] - 2026-08-24

### Added
* squash now has history and manifest.json file

### Changed
* enhance connection resolution for supported connection types

### Fixed
* change default database driver to sqlite and set migration template to raw

## [1.3.0] - 2026-08-23

### Added

* Added the `squash` command for consolidating executed schema migrations into a new baseline.
* Added `MigrationSquasher` for generating and managing squashed migration baselines.
* Added `SchemaDumper` for reading the current MySQL/MariaDB schema.
* Added automatic archiving of superseded schema migrations during squash.
* Added preservation of `PopulatorMigration` files during schema squashing.
* Added automatic retimestamping of preserved population migrations so they remain ordered after the generated baseline.
* Added support for preserving executed and pending population migration state during squash.
* Added configurable migration templates through the `template` configuration option.
* Added `raw` and `fluent` migration template modes.
* Added dedicated raw and fluent migration template generators.

### Changed

* Changed the default migration template to `raw`.
* Refactored migration creation so file creation and template generation are handled separately.
* Standardized generated database connection configuration across MySQL, PostgreSQL and SQLite.
* Connection configuration now keeps the same fields for every driver, including `sqlite_path`.
* Updated generated configuration to read database settings from `$_ENV`.
* Improved generated configuration documentation for PDO instances, closures and callable connections.
* Improved schema squash migration ordering and migration repository updates.
* Updated migration generation behavior to respect the configured default template.

### Fixed

* Fixed schema squashing so population migrations are not incorrectly merged into the generated schema baseline.
* Fixed preserved population migrations being ordered before a newly generated baseline.
* Fixed migration history replacement during squash so preserved population migration state remains consistent.

## [1.2.0] - 2026-07-22

### Added

* Added idempotent data population through `Populate`.
* Added population migration templates with the `--populate` option.
* Added conflict handling for MySQL, MariaDB, PostgreSQL and SQLite.
* Added support for updating selected columns when populated rows already exist.
* Added modified migration detection in the `status` command.
* Added `repair --modified` to accept the current checksum of intentionally modified migrations.
* Added PHP 8.5 to the CI test matrix.

### Changed

* Added rollback support with foreign key checks management.
* Changed unknown migration templates to generate wrapped raw SQL statements.
* Changed `refresh` to roll back all managed migrations and run them again.
* Improved migration creator templates and validation.
* Improved the test workflow by separating tests and code-quality checks.

### Deprecated

* Deprecated the `--blank` command option while keeping it functional.

### Fixed

* Fixed raw migration fallback generation so unknown migration names use `$this->raw(...)`.
* Fixed incorrect `fresh` behavior that previously acted like `refresh`.
* Fixed static-analysis type declarations in `Populate`.
* Fixed driver-specific SQL expectations for quoted identifiers.

### Removed

* Removed the `new` command alias for `make`.
* Removed the `-b` alias for `--blank`.

### Security

* Added migration checksum tracking.

## [1.1.0] - 2026-07-01

### Added

* Added smart migration template generation.
* Added driver-aware SQL templates.
* Added the `doctor` command.
* Added the `repair` command.
* Added the `--blank` option for empty migrations.
* Added the `new` alias for `make`.
* Added driver-aware SQL helpers.
* Added improved migration validation.

### Changed

* Smart templates are now generated by default.
* Simplified CLI architecture.
* Refactored internal application structure.
* Improved help output.
* Improved migration generation experience.

### Fixed

* Improved SQL formatting in generated migrations.
* Improved migration status detection.
* Improved driver compatibility.

## [1.0.0] - 2026-06-27

### Added

* First stable release.
* SQL-first migration engine.
* Raw SQL migrations.
* Lightweight SQL helpers.
* Migration batches.
* Rollback support.
* Dry-run mode.
* Interactive CLI.
* PDO, callable and array-based connections.
* Framework-agnostic architecture.

## [0.1.0] - 2026-06-15

### Added

* Initial public preview.
* Basic migration engine.
* Raw SQL migrations.
* Migration repository.
* Initial CLI.
