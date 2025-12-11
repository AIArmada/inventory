# PHPStan Guidelines

- All code must pass PHPStan level 6.
- **Never run PHPStan on the whole `packages` directory.** Run it per package you changed (e.g., `./vendor/bin/phpstan analyse --level=6 packages/inventory`).
- Verify with the per-package command (`phpstan.neon` baseline applies).
