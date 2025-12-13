---
description: Execute full-spectrum package audit
---

# Package Audit Workflow

This workflow executes a full-spectrum audit for a specific commerce package following the Auditor Agent specifications.

## Prerequisites
- Ensure you're in the commerce root directory
- Have vendor dependencies installed

## Workflow Steps

### Step 1: Identify Package to Audit
Determine which package to audit from the task file at `.agent/tasks/package-audit-task.md`

### Step 2: Explore Package Structure
```bash
ls -la packages/<package>/
ls packages/<package>/src/
ls packages/<package>/src/Models/ 2>/dev/null || echo "No Models directory"
ls packages/<package>/database/migrations/ 2>/dev/null || echo "No migrations"
```

### Step 3: Review Configuration
```bash
cat packages/<package>/config/*.php 2>/dev/null || echo "No config files"
```

### Step 4: Check composer.json for Dependencies
```bash
cat packages/<package>/composer.json | grep -A 20 '"require"'
cat packages/<package>/composer.json | grep -A 10 '"suggest"'
```

### Step 5: Run PHPStan Analysis
// turbo
```bash
./vendor/bin/phpstan analyse --level=6 packages/<package>
```

### Step 6: Run Package Tests
// turbo
```bash
./vendor/bin/pest tests/src/<PackageName> --parallel
```

### Step 7: Check Test Coverage (if XML exists)
```bash
./vendor/bin/phpunit .xml/<package>.xml --coverage 2>/dev/null || echo "No coverage XML found"
```

### Step 8: Run Pint Format Check
// turbo
```bash
./vendor/bin/pint packages/<package> --test
```

### Step 9: Audit Models
For each model in `packages/<package>/src/Models/`:
- Check for `HasUuids` trait
- Check for `getTable()` method (no `$table` property)
- Check for `booted()` with cascade handling
- Check for proper `$fillable` matching migrations
- Check for `$casts` array
- Check for PHPDoc `@property` annotations
- Check for typed relations with generics

### Step 10: Audit Migrations
For each migration in `packages/<package>/database/migrations/`:
- Check for `uuid('id')->primary()`
- Check for `foreignUuid()` WITHOUT `constrained()`
- Check for NO DB-level cascades
- Check for proper column types

### Step 11: Audit Service Provider
Check `packages/<package>/src/<Package>ServiceProvider.php`:
- Check for proper service bindings
- Check for conditional integrations via `class_exists()`
- Check for config publishing
- Check for migration publishing

### Step 12: Audit Config Usage
```bash
grep -r "config('<package>." packages/<package>/src/ | head -50
```

### Step 13: Document Issues
Create or update audit report at `packages/<package>/docs/vision/AUDIT.md`

### Step 14: Fix Issues
Apply all fixes for critical and high severity issues

### Step 15: Re-run Verification
// turbo
```bash
./vendor/bin/phpstan analyse --level=6 packages/<package>
./vendor/bin/pest tests/src/<PackageName> --parallel
./vendor/bin/pint packages/<package>
```

### Step 16: Update PROGRESS.md
If `packages/<package>/docs/vision/PROGRESS.md` exists, update with audit completion status

---

## Quick Commands Reference

```bash
# PHPStan per package
./vendor/bin/phpstan analyse --level=6 packages/<package>

# Tests per package
./vendor/bin/pest tests/src/<PackageName> --parallel

# Coverage per package
./vendor/bin/phpunit .xml/<package>.xml --coverage

# Pint per package
./vendor/bin/pint packages/<package>

# Search for config usage
grep -r "config('<package>." packages/<package>/src/

# Check for N+1 queries (look for missing eager loads)
grep -r "->get()" packages/<package>/src/ | head -20

# Check for raw queries
grep -r "DB::raw" packages/<package>/src/
grep -r "DB::select" packages/<package>/src/
```
