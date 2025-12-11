# Testing Guidelines

- **Never run the whole Pest suite**; always run by package only (e.g., `./vendor/bin/pest tests/src/Inventory --parallel`). Identify the package you touched and target that package's tests.
- When many failures: capture once, group by cause, batch-fix, rerun targeted files (`--filter` when needed) before full package run.
- Coverage: use package-specific XML in `.xml/`; create if missing. Target ≥85% for non-Filament packages. Commands: `./vendor/bin/pest tests/src/PackageName --parallel`, `./vendor/bin/phpunit .xml/package.xml --coverage`, `./vendor/bin/pest --coverage --min=85` when applicable.
