# Development Guidelines

- Before destructive changes, copy the file (e.g., `cp file.php file.php.bak`), then delete the backup when done.
- Be smart about scope: identify the package for any file you touch and run tooling only for that package.
- Pint: never run repo-wide; format only the affected package (e.g., `./vendor/bin/pint packages/inventory`).
