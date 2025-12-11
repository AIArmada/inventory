<laravel-boost-guidelines>
=== .ai/multitenancy rules ===

# Multitenancy Guidelines

All multitenancy support is provided by `commerce-support` package via owner-based polymorphic scoping.

## Core Components

- `OwnerResolverInterface` — implement to resolve current tenant/owner from your tenancy solution
- `NullOwnerResolver` — default no-op resolver (disables multitenancy)
- `HasOwner` trait — adds owner scoping to Eloquent models

## Migration Pattern

Add nullable polymorphic owner columns:
```php
Schema::create('shipping_zones', function (Blueprint $table) {
$table->uuid('id')->primary();
$table->nullableMorphs('owner'); // Creates owner_type and owner_id
// ... other columns
$table->timestamps();
});
```

## Model Pattern

```php
use AIArmada\CommerceSupport\Traits\HasOwner;

class ShippingZone extends Model
{
use HasOwner;

protected $fillable = [
'owner_type',
'owner_id',
// ... other fillables
];
}
```

## Resolver Implementation

Bind your resolver in a service provider:
```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

$this->app->bind(OwnerResolverInterface::class, function () {
return new class implements OwnerResolverInterface {
public function resolve(): ?Model
{
// Spatie multitenancy
return Tenant::current();

// Filament panels
return Filament::getTenant();

// User's store
return auth()->user()?->currentStore;
}
};
});
```

## Query Scoping

```php
$owner = app(OwnerResolverInterface::class)->resolve();

// Get owner's records + global records
Model::forOwner($owner)->get();

// Get owner's records only (exclude global)
Model::forOwner($owner, includeGlobal: false)->get();

// Get only global records
Model::globalOnly()->get();
```

## HasOwner Trait Methods

| Method | Description |
|--------|-------------|
| `owner()` | Polymorphic MorphTo relationship |
| `scopeForOwner($owner, $includeGlobal)` | Scope to owner ± global records |
| `scopeGlobalOnly()` | Scope to ownerless records only |
| `hasOwner()` | Check if owner is assigned |
| `isGlobal()` | Check if no owner (global) |
| `belongsToOwner($owner)` | Check specific owner match |
| `assignOwner($owner)` | Assign owner to model |
| `removeOwner()` | Clear owner (make global) |
| `owner_display_name` | Human-readable owner name accessor |

## Verification

- Models with `HasOwner` must have `owner_type` and `owner_id` in fillables and migration
- Queries in multi-tenant contexts must use `forOwner()` scope
- Test both owner-scoped and global record scenarios


=== .ai/development rules ===

# Development Guidelines

- Before destructive changes, copy the file (e.g., `cp file.php file.php.bak`), then delete the backup when done.
- Be smart about scope: identify the package for any file you touch and run tooling only for that package.
- Pint: never run repo-wide; format only the affected package (e.g., `./vendor/bin/pint packages/inventory`).


=== .ai/model rules ===

<?php /** @var \Illuminate\View\ComponentAttributeBag $attributes */ ?>
## Model Guidelines

- No DB-level FK constraints or cascades; handle all cascades in application code.
- Required structure: use `HasUuids`; no `$table` property; `getTable()` pulls from config with prefix fallback; fillables match migration.
- Relations typed with generics and PHPDoc properties.
- `booted()` must implement application-level cascades (delete children or null FK as appropriate).
- `casts()` set for arrays/booleans/datetimes as needed.
- Migration reminder: use `foreignUuid()` without `constrained()`/cascades.


=== .ai/database rules ===

# Database Guidelines

- Primary keys: `uuid('id')->primary()` only.
- Foreign keys: `foreignUuid('relation_id')`; never use `constrained()` or DB-level cascades—handle in application logic.
- Sample:
```php
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id');
    $table->foreignUuid('cart_id');
    $table->timestamps();
});
```
- Verify migrations contain no DB constraints; ensure cascades are implemented in models/services instead.


=== .ai/docs rules ===

# Documentation Guidelines (Filament-Style)

Documentation follows Filament's structure: markdown files with Astro component imports stored in the main repo, consumed by a separate docs site.

## How Filament Does It

1. **Markdown in main repo** - `docs/` and `packages/*/docs/` contain plain markdown
2. **Astro imports in markdown** - Files include `import Aside from "@components/Aside.astro"` 
3. **Separate docs site** - A separate repository/project builds the actual website
4. **Docs site pulls markdown** - The Astro site copies/imports markdown from the main repo

## File Structure

### Naming Convention
```
packages/<package>/docs/
├── 01-overview.md           # Package introduction
├── 02-installation.md       # Setup instructions
├── 03-configuration.md      # Config options
├── 04-usage.md              # Basic usage
├── 05-<feature>.md          # Feature-specific docs
├── ...
└── 99-troubleshooting.md    # Common issues
```

- Use numbered prefixes (`01-`, `02-`) for ordering
- Use lowercase kebab-case for filenames
- One topic per file, max 500 lines

### Frontmatter (Required)
Every markdown file must have YAML frontmatter:

```yaml
---
title: Getting Started
---
```

Optional frontmatter fields:
```yaml
---
title: Overview
contents: false           # Hide table of contents
---
```

## Astro Components (For Future Docs Site)

Prepare markdown with Astro component imports that will work when the docs site is built:

```md
---
title: Configuration
---
import Aside from "@components/Aside.astro"
import AutoScreenshot from "@components/AutoScreenshot.astro"

## Introduction

<Aside variant="info">
    This feature requires PHP 8.4 or higher.
</Aside>

<Aside variant="warning">
    Breaking change in v2.0: The `oldMethod()` has been renamed to `newMethod()`.
</Aside>
```

### Available Components

| Component | Purpose | Variants |
|-----------|---------|----------|
| `<Aside>` | Callouts/alerts | `info`, `warning`, `tip`, `danger` |
| `<AutoScreenshot>` | Versioned screenshots | `version="1.x"` |
| `<Disclosure>` | Collapsible sections | - |

## Content Style

### Code Examples
Always include working, copy-paste ready examples:

```php
use AIArmada\Cart\Facades\Cart;

Cart::session('user-123')
    ->add([
        'id' => 'product-1',
        'name' => 'Product Name',
        'price' => 99.99,
        'quantity' => 1,
    ]);
```

### Headings
- `##` for main sections
- `###` for subsections
- `####` sparingly for deep nesting
- Never skip heading levels

### Links
Cross-reference related documentation:
```md
See the [configuration](configuration) documentation for details.
For panel setup, visit the [introduction/installation](../introduction/installation).
```

## Package Documentation Structure

Each package must have a `docs/` folder with:

1. **01-overview.md** - What it does, key features
2. **02-installation.md** - Composer, config, migrations
3. **03-configuration.md** - All config options explained
4. **04-usage.md** - Basic usage patterns
5. **Feature docs** - One file per major feature (numbered)
6. **99-troubleshooting.md** - Common issues and solutions

## Hosting on Dedicated Domain

### Option 1: Separate Docs Repository (Filament's Approach)

Create a separate repository for the docs site:

```
commerce-docs/           # Separate repo
├── astro.config.mjs
├── package.json
├── src/
│   ├── content/
│   │   └── docs/        # Markdown copied/synced from main repo
│   └── components/
│       ├── Aside.astro
│       ├── AutoScreenshot.astro
│       └── Disclosure.astro
└── scripts/
    └── sync-docs.js     # Script to pull docs from main repo
```

### Option 2: Monorepo Subfolder

Keep docs site in the main repo:

```
commerce/
├── packages/
├── docs-site/           # Astro project
│   ├── astro.config.mjs
│   ├── src/content/docs/
│   └── scripts/sync-docs.js
└── ...
```

### Setup Steps

```bash
# Create docs site (in separate repo or subfolder)
npm create astro@latest docs-site -- --template starlight

cd docs-site

# Configure astro.config.mjs
```

```js
// astro.config.mjs
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

export default defineConfig({
  site: 'https://docs.commerce.dev',
  integrations: [
    starlight({
      title: 'Commerce Docs',
      social: { github: 'https://github.com/AIArmada/commerce' },
      sidebar: [
        { label: 'Getting Started', autogenerate: { directory: 'getting-started' } },
        { label: 'Cart', autogenerate: { directory: 'cart' } },
        { label: 'Cashier', autogenerate: { directory: 'cashier' } },
        { label: 'Chip', autogenerate: { directory: 'chip' } },
        { label: 'Vouchers', autogenerate: { directory: 'vouchers' } },
      ],
    }),
  ],
});
```

### Sync Script

```js
// scripts/sync-docs.js
const fs = require('fs');
const path = require('path');

const MAIN_REPO = process.env.COMMERCE_REPO || '../commerce';
const DEST = path.join(__dirname, '../src/content/docs');

const packages = [
  'cart', 'cashier', 'cashier-chip', 'chip', 
  'vouchers', 'inventory', 'stock', 'docs'
];

// Clean destination
fs.rmSync(DEST, { recursive: true, force: true });
fs.mkdirSync(DEST, { recursive: true });

// Copy package docs
packages.forEach(pkg => {
  const src = path.join(MAIN_REPO, 'packages', pkg, 'docs');
  const dest = path.join(DEST, pkg);
  if (fs.existsSync(src)) {
    fs.cpSync(src, dest, { recursive: true });
    console.log(`✓ Copied ${pkg}/docs`);
  }
});

console.log('Docs synced!');
```

### Deployment

| Platform | Setup |
|----------|-------|
| **Vercel** | Connect repo → Auto-detects Astro → Deploy |
| **Netlify** | Build: `npm run build`, Publish: `dist` |
| **Cloudflare Pages** | Build: `npm run build`, Output: `dist` |
| **GitHub Pages** | Use GitHub Actions with `withastro/action@v3` |

### GitHub Actions (for separate repo)

```yaml
# .github/workflows/deploy.yml
name: Deploy Docs

on:
  push:
    branches: [main]
  repository_dispatch:
    types: [docs-update]  # Triggered from main repo

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Clone main repo for docs
        run: |
          git clone --depth 1 https://github.com/AIArmada/commerce.git ../commerce
          node scripts/sync-docs.js
      
      - uses: withastro/action@v3
```

### Domain Configuration

1. Add custom domain in hosting platform dashboard
2. Configure DNS:
   ```
   CNAME docs.commerce.dev → your-site.vercel.app
   ```
3. HTTPS is automatic on all major platforms

## Verification

```bash
# Check all packages have required docs
for pkg in cart cashier chip vouchers; do
  ls packages/$pkg/docs/01-*.md 2>/dev/null || echo "Missing: $pkg"
done

# Validate frontmatter exists
grep -L "^---" packages/*/docs/*.md

# Check for numbered prefixes
ls packages/*/docs/*.md | grep -v "/[0-9][0-9]-"
```

## Content Checklist

- [ ] Every config key has documentation
- [ ] Every public method has examples  
- [ ] Every event is documented
- [ ] Breaking changes have migration guides
- [ ] Files use numbered prefixes for ordering
- [ ] All files have frontmatter with `title:`


=== .ai/phpstan rules ===

# PHPStan Guidelines

- All code must pass PHPStan level 6.
- **Never run PHPStan on the whole `packages` directory.** Run it per package you changed (e.g., `./vendor/bin/phpstan analyse --level=6 packages/inventory`).
- Verify with the per-package command (`phpstan.neon` baseline applies).


=== .ai/packages rules ===

# Packages Guidelines

- Independence: each package must run standalone; prefer `suggest`/optional deps over `require`.
- Integration: when co-installed, auto-enable hooks via service providers using `class_exists()`/config toggles.
- DTOs: all DTOs must use Laravel Data for consistency.
- Example integration pattern:
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
- Verification: test package alone via `composer require package/<pkg>` and together to confirm auto-features.


=== .ai/config rules ===

# Config Guidelines

- Only keep config keys that are used in code.
- Order core package configs: Database → Credentials/API → Defaults → Features/Behavior → Integrations → HTTP → Webhooks → Cache → Logging.
- Order Filament configs: Navigation → Tables → Features → Resources.
- Keep configs minimal; publish only what is needed; nest related settings.
- Migrations with JSON columns require a `json_column_type` config key.
- Prefer defaults over excess env() wrappers; remove unused keys.
- Comments: Laravel-style section headers only; inline comments only for non-obvious values.
- Verify with `grep -r "config('package.key')" src/ packages/*/src/`; remove keys with no matches.


=== .ai/test rules ===

# Testing Guidelines

- **Never run the whole Pest suite**; always run by package only (e.g., `./vendor/bin/pest tests/src/Inventory --parallel`). Identify the package you touched and target that package's tests.
- When many failures: capture once, group by cause, batch-fix, rerun targeted files (`--filter` when needed) before full package run.
- Coverage: use package-specific XML in `.xml/`; create if missing. Target ≥85% for non-Filament packages. Commands: `./vendor/bin/pest tests/src/PackageName --parallel`, `./vendor/bin/phpunit .xml/package.xml --coverage`, `./vendor/bin/pest --coverage --min=85` when applicable.


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
