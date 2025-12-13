# Development Guidelines

- **NEVER** do any repo "cleanup" without explicit user instruction/permission.
	- This includes (but is not limited to): `git restore`, `git checkout -- <path>`, `git reset`, `git clean`, removing untracked files, mass-reverting changes, or otherwise trying to "get back to a clean state".
	- If the working tree is messy or another agent is changing files: stop and ask what to do.
- Before destructive changes, copy the file (e.g., `cp file.php file.php.bak`), then delete the backup when done.
- Be smart about scope: identify the package for any file you touch and run tooling only for that package.
- Pint: never run repo-wide; format only the affected package (e.g., `./vendor/bin/pint packages/inventory`).
