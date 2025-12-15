# Commerce Custom Guidelines

## Config
```
# Config Guidelines

All configuration options must be actively used or implemented in the codebase.

## Standard Config Order

Config files MUST follow this section order:

### Core Package Configs
1. Database - Tables, prefixes, JSON column types
2. Credentials/API - Keys, secrets, environment
3. Defaults - Currency, tax rates, default values
4. Features/Behavior - Core feature toggles
5. Integrations - Other package integrations
6. HTTP - Timeouts, retries
7. Webhooks - Webhook configuration
8. Cache - Caching settings
9. Logging - Logging configuration

### Filament Package Configs
1. Navigation - Group, sort order
2. Tables - Polling, formats
3. Features - Feature toggles
4. Resources - Resource-specific settings

## Rules
- If a config key is defined but not referenced anywhere, remove it.
- Publish only necessary configs via `php artisan vendor:publish`.
- Keep `config/*.php` files minimal and purposeful.
- Packages with JSON columns in migrations MUST have `json_column_type` config.
- Use compact section headers (single line description only).
- Group related settings under nested arrays.
- Prefer opinionated defaults over excessive configuration.
- Remove redundant env() wrappers for non-sensitive hardcoded values.

## Comment Style
Use compact Laravel-style section headers. Inline comments only for non-obvious values.

## Verification
Search codebase for config key usage:
```bash
grep -r "config('package.key')" src/ packages/*/src/
```
If no matches, remove the config.

## Comment Style
Use compact Laravel-style section headers. Inline comments only for non-obvious values.

## Verification
Search codebase for config key usage:
```bash
grep -r "config('package.key')" src/ packages/*/src/
```
If no matches, remove the config.
```
## Database
```
# Database Guidelines

## Primary Keys
- All tables must use `uuid('id')->primary()` for primary key.

## Foreign Keys
- Use `foreignUuid('relation_id')` for foreign key columns.
- **Do NOT** add `->constrained()`, `->cascadeOnDelete()`, or any DB-level constraints/cascading.
- Application logic must handle referential integrity and cascades.

## Example Migration
```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id');
    $table->foreignUuid('cart_id');
    $table->timestamps();
});
```

## Verification
- Review migrations: no `constrained()` or cascade methods on foreign keys.
- Ensure Eloquent relations handle cascades (e.g., `cascadeOnDelete()` in models).
```
## Models
```
## Model Guidelines

**CRITICAL**: Never use database-level foreign key constraints or cascades (`->constrained()`, `->cascadeOnDelete()`). Handle all referential integrity and cascading **in application code only**.

### Required Model Structure

```php
<?php

declare(strict_types=1);

namespace {{ $namespace }}\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, {{ $childModel }}> ${{ $childPlural }}
 */
class {{ $modelClass }} extends Model
{
    use HasUuids;

    protected $fillable = [
        // List fillable columns matching migration
    ];

    public function getTable(): string
    {
        $tables = config('{{ $configKey }}.database.tables', []);
        $prefix = config('{{ $configKey }}.database.table_prefix', '{{ $tablePrefix}}_');

        return $tables['{{ $tableKey }}'] ?? $prefix.'{{ $tableName }}';
    }

    /**
     * @return HasMany<{{ $childModel }}, $this>
     */
    public function {{ $childPlural }}(): HasMany
    {
        return $this->hasMany({{ $childModel }}::class, '{{ $foreignKey }}');
    }

    /**
     * @return BelongsTo<{{ $parentModel }}, $this>
     */
    public function {{ $parentSnake }}(): BelongsTo
    {
        return $this->belongsTo({{ $parentModel }}::class, '{{ $foreignKey }}');
    }

    /**
     * Application-level cascade delete (NO database constraints!)
     */
    protected static function booted(): void
    {
        static::deleting(function ({{ $modelClass }} ${{ $modelVar }}): void {
            ${{ $modelVar }}->{{ $childPlural }}()->delete();
            // Add other cascades as needed
            // For nullable FKs: ${{ $modelVar }}->{{ $childPlural }}()->update(['{{ $foreignKey }}' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            // Casts for dates, JSON, booleans, enums
            '{{ $jsonField }}' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
```

### Cascade Rules

| Relationship | Delete Action | Example |
|--------------|---------------|---------|
| `hasMany` children | `->delete()` | `$order->items()->delete();` |
| Nullable FK children | `->update(['fk' => null])` | `$order->webhookLogs()->update(['order_id' => null]);` |

### Verification Checklist
- ✅ `HasUuids` trait
- ✅ `getTable()` from config (no hardcoded names)
- ✅ `booted()` with cascade deletes
- ✅ **NO** `protected $table` property
- ✅ PHPDoc `@property` annotations
- ✅ Type-safe relations with generics
- ✅ PHPStan level 6 compliant

**Migration**: Use `foreignUuid('order_id')` **without** `->constrained()` or cascades.
```
## Docs
```
# Documentation Guidelines (Filament-Style)

Docs are stored as markdown in the main repo, with a separate site that builds them.

## File Structure

### Naming Convention
- Use numbered prefixes for ordering: `01-overview.md`, `02-installation.md`
- Use lowercase kebab-case: `03-getting-started.md`
- One topic per file, max ~500 lines

### Required Frontmatter
Every markdown file MUST start with:
```yaml
---
title: Page Title
---
```

### Astro Component Imports (for future docs site)
Add after frontmatter for rich content:
```md
---
title: Configuration
---
import Aside from "@components/Aside.astro"

<Aside variant="warning">
    Breaking change in v2.0...
</Aside>
```

## Package docs/ Structure

Each package must have:
1. `01-overview.md` - Introduction, features
2. `02-installation.md` - Composer, config, migrations
3. `03-configuration.md` - All config options
4. `04-usage.md` - Basic usage patterns
5. Feature-specific docs (numbered)
6. `99-troubleshooting.md` - Common issues

## Content Style
- `##` for main sections, `###` for subsections
- Working code examples with full imports
- Cross-reference related docs with relative links
- Use `<Aside>` components for callouts

## Verification
```bash
# Check frontmatter exists
grep -L "^---" packages/*/docs/*.md

# Find docs without numbered prefix
ls packages/*/docs/*.md | grep -v "/[0-9][0-9]-"
```
```
## Packages
```
# Packages Guidelines

## Independence
- Packages must work fully standalone without requiring other commerce packages.
- Use `suggest` or optional dependencies in `composer.json`, not `require`.

## Tight Integration
- When related packages are installed together, enable seamless integrations:
  - Auto-setup relations, events, middleware via service provider checks.
  - Use `class_exists()` or `config('package.enabled')` for conditional features.

## Example Service Provider
```php
public function boot(): void
{
    if (class_exists(Cashier::class)) {
        // Cart-Cashier integration
    }
    
    if (class_exists(Chip::class)) {
        // Cart-Chip integration
    }
}
```

## Verification
- Test standalone: `composer require package/cart`
- Test integrated: Install multiple, verify auto-features.
```
## PHPStan
```
# PHPStan Guidelines

PHPStan must pass at level 6 for all code.

## Verification

Run the following command to verify:

```bash
./vendor/bin/phpstan analyse --level=6
```

## Configuration

The project's `phpstan.neon` configures the baseline. Ensure no errors at level 6 before merging changes.
```
## Test
```
# Testing Guidelines

## Running Tests

**Don't run all tests at once. The test suite is too large and inefficient. Always test by individual package using `tests/src/PackageName`.**

Use `--parallel` flag to speed up test execution:

```bash
./vendor/bin/pest tests/src/PackageName --parallel
```

## Fixing Multiple Test Failures

When fixing tests that have many failures:

1. **Record failures first** - Run tests once and capture all failing test names/locations to a file before making any fixes
2. **Analyze patterns** - Group failures by root cause (e.g., missing field, wrong assertion, invalid test data)
3. **Batch fixes** - Fix all related issues together before re-running tests
4. **Avoid repeated runs** - Test suites are large and slow; minimize full test runs by:
   - Fixing all identified issues in one pass
   - Running only the specific test file during development: `./vendor/bin/pest tests/path/to/TestFile.php`
   - Using `--filter` to run specific test cases when debugging

Example workflow:
```bash
# 1. Run once and capture failures
./vendor/bin/pest tests/src/PackageName --configuration=.xml/package.xml 2>&1 | tee test-failures.txt

# 2. Fix all issues based on the captured output

# 3. Run specific test file to verify fixes
./vendor/bin/pest tests/src/PackageName/Unit/SpecificTest.php --configuration=.xml/package.xml

# 4. Run full suite only after individual files pass
./vendor/bin/pest tests/src/PackageName --parallel --configuration=.xml/package.xml
```

## Coverage

- Scope coverage to specific packages using dedicated PHPUnit XML configs inside .xml folder (e.g., `cart.xml`, `vouchers.xml`).
- Create `package.xml` if it doesn't exist, following the structure of existing ones (bootstrap autoload, testsuite directory, source include, env vars).
- Run coverage:

```bash
./vendor/bin/phpunit .xml/package.xml --coverage
```

- All non filament packages must achieve **minimum 85% coverage**.
- Verify with `./vendor/bin/pest --coverage --min=85` for workspace-wide checks when applicable.

```
## File Safety
```
# File Safety Guidelines

## Git / Working Tree Safety
- **NEVER** do any repo "cleanup" without explicit user instruction/permission.
- This includes (but is not limited to): `git restore`, `git checkout -- <path>`, `git reset`, `git clean`, removing untracked files, mass-reverting changes, or otherwise trying to "get back to a clean state".
- If the working tree is messy or another agent is changing files: stop and ask what to do.

## Backup Before Removal
- **ALWAYS** backup files before removing or replacing them.
- Use `cp file.php file.php.bak` before any destructive operation.
- Remove the backup file after successful completion.
- Never delete files without creating a backup first.

## Verification
- Confirm backup exists before proceeding with removal.
- Run tests after changes to ensure nothing is broken.
- Only delete backup after all tests pass.
```

<laravel-boost-guidelines>
=== .ai/test rules ===

# Testing Guidelines
- **Goal**: ELIMINATE BUGS.
- **Refs**:
  - [Overview](https://filamentphp.com/docs/4.x/testing/overview)
  - [Resources](https://filamentphp.com/docs/4.x/testing/testing-resources)
  - [Tables](https://filamentphp.com/docs/4.x/testing/testing-tables)
  - [Schemas](https://filamentphp.com/docs/4.x/testing/testing-schemas)
  - [Actions](https://filamentphp.com/docs/4.x/testing/testing-actions)
  - [Notifications](https://filamentphp.com/docs/4.x/testing/testing-notifications)
- **Exec**:
- **Single**: `./vendor/bin/pest path/to/Test.php`.
- **Dir**: `./vendor/bin/pest path/to/dir`.
- **Full**: `./vendor/bin/pest --parallel ...` (Final only).
- **Coverage**:
- Don't run full if `0% files > 10%`.
- Command: `./vendor/bin/pest --coverage ...`.
- Targets: Core ≥80%, Filament ≥70%, Support ≥80%.
- **Output**: ALWAYS pipe: `2>&1 | tee /tmp/out.txt`.


=== .ai/multitenancy rules ===

# Multitenancy Guidelines
- **Pkg**: `commerce-support`.
- **Impl**:
- Mig: `$table->nullableMorphs('owner')`.
- Model: `use HasOwner`.
- Provider: Configure `COMMERCE_OWNER_RESOLVER` (or bind `OwnerResolverInterface` once, centrally via commerce-support).
- **Usage**:
- Owner: `Model::forOwner($owner)->get()`.
- Global: `Model::globalOnly()->get()`.


=== .ai/phpstan rules ===

# PHPStan Guidelines
- **Lvl**: 6.
- **Scope**: Per package (`packages/pkg/src`).
- **Rules**:
- Respect `phpstan.neon`.
- NO new `ignoreErrors` unless exhausted.
- Fix root causes over suppression.


=== .ai/spatie rules ===

# Spatie Guidelines
- **DTO**: `spatie/laravel-data`.
- **Logs**: `activitylog` (Business), `auditing` (Compliance).
- **Filament**: Official plugins MANDATORY.
- **Webhooks**: `webhook-client` (Idempotent Job).
- **Media**: `medialibrary`.
- **Settings**: `laravel-settings`.
- **Tags**: `laravel-tags`.
- **States**: `model-states`.


=== .ai/development rules ===

# Development Guidelines
- **Safety**: NEVER "cleanup" or mass-revert without permission.
- **Scope**: Run tools (Pint/PHPStan) ONLY on modified packages.

## Best Practices
- **Strict Laravel**: `Arr::get()`, `Collections`, `Service Container`.
- **Modern PHP**: 8.2+ (readonly, match).
- **Logic**: Action Classes only. No logic in Controllers/Models.
- **Structure**: SOLID, Repository for access, Factory for creation.

## Naming
- **Classes**: `PascalCase`.
- **Methods/Vars**: `camelCase`.
- **Consts**: `SCREAMING_SNAKE`.
- **DB**: `snake_case` (tables/cols).
- **Bool**: `is_`, `has_`, `can_`.

## Agents
- **Auditor**: Strict auditing/security (`.github/agents/Auditor.agent.md`).
- **QC**: QA/Testing (`.github/agents/QC.agent.md`).
- **Visionary**: Architecture (`.github/agents/Visionary.agent.md`).

## Beta Status
- **Break Changes**: Allowed for improvement. No backward compatibility required.


=== .ai/filament rules ===

# Filament Guidelines
- **Ver**: Filament v5 API Mandatory.
- **Spatie**: MUST use official Filament plugins (Tags, Settings, Media, Fonts).
- **Actions**: Use built-in `Import`/`Export` actions only.
- **Verification**: Verify all signatures against v5 docs.


=== .ai/config rules ===

# Config Guidelines
- **Keys**: Keep minimal, remove unused (verify via grep).
- **Structure**:
  - Core: DB -> Creds -> Defaults -> Features -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
  - Filament: Nav -> Tables -> Features -> Resources.
- **Rules**:
  - Use `json_column_type` for JSON/Migration.
  - Prefer defaults over excessive `env()`.
  - Comments: Section headers only, inline for non-obvious.


=== .ai/packages rules ===

# Packages Guidelines
- **Indep**: Must run standalone. `suggest` over `require`.
- **Integ**: Auto-enable via `class_exists()` check in `boot()`.
- **Code**: All DTOs via `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Test**: Verify standalone install and integration.


=== .ai/docs rules ===

# Documentation Guidelines
- **Loc**: `packages/<pkg>/docs/`.
  - **Files**: `01-overview`, `02-install`, `03-config`, `04-usage`, `99-trouble`.
  - **Fmt**: Markdown + YAML Frontmatter (`title:`).

  ## Features
  - **Components**: Use `import Aside from "@components/Aside.astro"`.
  - **Variants**: `info`, `warning`, `tip`, `danger`.
  - **Content**: Copy-paste ready code examples. `##` headers. Explains breaking changes.


=== .ai/model rules ===

# Model Guidelines
- **Base**: `HasUuids`, no `$table` property (use config).
- **Relations**: Typed with generics (PHPDoc).
- **Cascades**: Handle in `booted()` (delete/null). NO DB cascades.
- **Migration**: `foreignUuid()` only.


=== .ai/database rules ===

# Database Guidelines
- **PK**: `uuid('id')->primary()`.
- **FK**: `foreignUuid('col')` only. NO `constrained()` or DB-level cascades.
- **Cascades**: Handle in Application Logic (Model/Service).
- **Schema**: No `down()` logic needed.
- **Rules**: Ensure migrations are safe and idempotent.


=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.15

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>
