<laravel-boost-guidelines>
=== .ai/00-overview rules ===

# AI Guidelines Overview (Monorepo Contract)

These files are intentionally split by concern for easier maintenance. Read and apply **all** of them.

## How to apply
- **Follow the strictest rule when in doubt** (security > data isolation > correctness > style).
- **If instructions conflict or are impossible**, say so explicitly, explain why, and propose the safest alternative.
- **Never assume UI scoping is security**. Server-side enforcement and validation are mandatory.

## Runtime assumptions
- **PHP**: Target **PHP 8.4+** only.
- **Filament**: Use Filament v5 APIs. Filament v5 is API-compatible with Filament v4; the primary difference is Livewire (v5 uses Livewire v4, v4 uses Livewire v3). When official v5 docs are missing, Filament v4 docs/examples are acceptable.

## Verification mindset
- Prefer **small, auditable changes** over broad refactors.
- Use per-package checks (tests/PHPStan) instead of repo-wide runs.
- When a guideline requires verification, either run it (if feasible) or call out what must be run by the user.


=== .ai/filament rules ===

# Filament Guidelines
- **Version**: Filament v5.
  - Filament v5 is API-compatible with Filament v4; the main difference is Livewire (v5 uses Livewire v4, v4 uses Livewire v3).
  - When v5 docs are incomplete, v4 docs/examples are acceptable.
- **Spatie**: MUST use official Filament plugins (Tags, Settings, Media, Fonts).
- **Actions**: Use built-in `Import`/`Export` actions only.
- **Multitenancy**: Filament tenancy is NOT sufficient; all queries and action handlers must still obey the owner-scoping contract.

## Verification
- Double-check method signatures in the installed Filament version before shipping.


=== .ai/multitenancy rules ===

# Multitenancy Guidelines

## Monorepo Contract
- **Boundary is mandatory**: Every package that stores tenant-owned data MUST define an explicit tenant boundary and enforce it on **every** read/write path.
- **Single source of truth**: Multi-tenancy primitives live in `commerce-support`.
- **No UI trust**: Filament form options are not security. Always validate on the server.
- **Column semantics**: `owner_type/owner_id` is reserved for the tenant boundary owner (rename any non-tenant uses).

## Design defaults (align ecosystem behavior)
- **Default enforcement**: prefer **default-on** owner enforcement (global scope) when owner mode is enabled.
- **Default include-global**: `false` unless explicitly required by business rules.
- **Meaning of `owner = null`**: treat as **global-only** records.
- **Cross-tenant/system operations**: allowed only when the call site uses an explicit, greppable opt-out.

## Data Model (Required)
- **Migration**: `$table->nullableMorphs('owner')` for tenant-owned tables.
- **Model**: `use HasOwner` (from `commerce-support`).
- **Provider**: Bind `OwnerResolverInterface` to resolve current owner context.
- **Non-tenant "ownership"** (wallet holder, payee, actor, etc.) MUST use different column names/relationships.

## Enforcement (Default-on)
- Tenant-owned `HasOwner` models SHOULD be protected by commerce-support's default-on owner global scope when owner mode is enabled.
- **Escape hatch**: intentionally cross-tenant/system operations MUST use an explicit, greppable opt-out (e.g. `->withoutOwnerScope()` / `withoutGlobalScope(OwnerScope::class)`).
- **Non-request surfaces** (jobs/commands/schedules): MUST NOT rely on ambient web auth. Pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Query Rules (Non-negotiable)
- **Reads**: Must be owner-enforced on every surface (UI + non-UI). Prefer default-on enforcement; use `Model::forOwner($owner)` only when you are intentionally selecting an owner context and/or explicitly including global rows.
- **Writes**: Any inbound foreign IDs (e.g., `location_id`, `order_id`, `batch_id`) MUST be validated as belonging to the current owner scope before attach/update.
- **Query builder**: `DB::table(...)` paths touching tenant-owned data MUST apply the query-builder owner scoping helper (Eloquent global scopes do not apply).
- **Global rows**: If a package supports global rows, provide clear semantics:
  - `Model::forOwner($owner)` (owner-only)
  - `Model::globalOnly()` (global-only)
  - Optional: include-global behavior must be explicit and consistent.

## Filament Rules
- **Resources**: Ensure `getEloquentQuery()` is owner-safe (default-on scope or explicit scoping). Don’t rely solely on `$tenantOwnershipRelationshipName` or UI filters.
- **Actions**: Validate IDs again inside `->action()` handlers (defense-in-depth).
- **Relationship selects**: Scope option queries and validate submitted IDs.

## Routing & Binding
- Route-model binding/download routes MUST NOT resolve cross-tenant rows for `HasOwner` models.
- Prefer hardened binding patterns where applicable (e.g., bind with an owner-safe query, or resolve via an action that enforces owner).

## Non-UI Surfaces
- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks MUST apply the same owner scoping as HTTP/Filament.
- Jobs/commands MUST NOT rely on ambient web auth to resolve owner; pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Verification (Required)
  - Cross-tenant writes throw/abort.
- Grep for unscoped entrypoints:
  - `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
  - `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
  - `rg -n -- "DB::table\(" packages/<pkg>/src`
  - `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
  - `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`


=== .ai/development rules ===

# Development Guidelines
- **Safety**: NEVER "cleanup" or mass-revert without permission.
- **Scope**: Run tools (Pint/PHPStan) ONLY on modified packages.

## Monorepo Formatting
- **Golden rule**: No style-only PRs.
- If touching `packages/*/src/**`, run Pint only on changed files (or at least only the changed packages).
- Never run Pint repo-wide “just to be safe” — it creates noisy diffs across unrelated packages.

## Best Practices
- **Strict Laravel**: `Arr::get()`, `Collections`, `Service Container`.
- **Modern PHP**: 8.4+ (readonly, match, modern typing).
- **Time**: Use `CarbonImmutable` (or immutable date/time objects) wherever possible; avoid mutable `Carbon` unless you have a strong reason.
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


=== .ai/model rules ===

# Model Guidelines
- **Base**:
  - Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
  - Do NOT set `protected $table`; implement `getTable()` using package config (tables map + prefix).
- **Relations**: type relations and collections with PHPDoc generics.
- **Cascades**: implement application-level cascades in `booted()` (delete or null-out). Never rely on DB cascades.
- **Migrations**: use `foreignUuid()` only (no `constrained()` / FK constraints).

## Verification
- Search for forbidden DB cascades/constraints in migrations: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`


=== .ai/database rules ===

# Database Guidelines
- **Primary keys**: `uuid('id')->primary()`.
- **Foreign keys**: `foreignUuid('col')` only.
- **Never** add DB-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- **Cascades/integrity**: enforce in application logic (models/actions/services).
- **Migrations**: keep safe/idempotent; no `down()` required.

## Verification
- Ensure no constraints/cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`


=== .ai/spatie rules ===

# Spatie Guidelines
- **DTOs**: `spatie/laravel-data`
- **Logging**: `activitylog` (business events), `auditing` (compliance)
- **Webhooks**: `spatie/laravel-webhook-client` (idempotent job pattern)
- **Media**: `spatie/laravel-medialibrary`
- **Settings**: `spatie/laravel-settings`
- **Tags**: `spatie/laravel-tags`
- **States**: `spatie/laravel-model-states`

## Rule of thumb
- If one of the above solves the problem, prefer it over inventing a custom subsystem.


=== .ai/docs rules ===

# Documentation Guidelines
- **Location**: `packages/<pkg>/docs/`
- **Required files**: `01-overview`, `02-install`, `03-config`, `04-usage`, `99-trouble`
- **Format**: Markdown with YAML frontmatter (`title:`) at the top of every file.

## Content rules
- Use `##` for main sections, `###` for subsections.
- Examples must be copy-paste ready (include imports/namespaces where relevant).
- Cross-reference related docs using relative links.
- Call out breaking changes explicitly and explain the migration path.

## Callouts
- Import: `import Aside from "@components/Aside.astro"`
- Variants: `info`, `warning`, `tip`, `danger`


=== .ai/phpstan rules ===

# PHPStan Guidelines
- **Level**: 6
- **Scope**: per package (e.g. `packages/<pkg>/src`), not repo-wide.
- **Rules**:
  - Respect `phpstan.neon` / `phpstan-baseline.neon`.
  - Do not add new `ignoreErrors` unless root-cause fixes are exhausted.
  - Prefer real fixes over suppression.

## Verification
- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`


=== .ai/packages rules ===

# Packages Guidelines
- **Independence**: Packages must work standalone. Prefer `suggest` over hard `require` for optional integrations.
- **Foundation**: Always check `commerce-support` for existing primitives, traits, or contracts before building custom logic or requiring external packages directly.
- **Integration**: When related packages are installed together, auto-enable integrations via `class_exists()` checks in service providers.
- **DTOs**: Use `spatie/laravel-data`.
- **Deletes**: No soft deletes (`SoftDeletes`).
- **Testing**: Verify both standalone install and integrated behavior.


=== .ai/config rules ===

# Config Guidelines
- **Keys**: Keep minimal. If a key is defined but never read, remove it.
- **Section order** (keep consistent across packages):
  - Core: Database -> Credentials/API -> Defaults -> Features/Behavior -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
  - Filament: Navigation -> Tables -> Features -> Resources.
- **Rules**:
  - Any package that uses JSON columns in migrations MUST define and use a `json_column_type` setting.
  - Prefer opinionated defaults over excessive `env()` usage (only use env vars for secrets or deploy-time values).
  - Comments: section headers only; inline comments only for non-obvious values.

## Verification
- Find config reads: `rg -n -- "config\('" packages/*/src packages/*/config`
- Find unused keys (typical pattern): `rg -n -- "config\('pkg\." packages/*/config | cat`


=== .ai/test rules ===

# Testing Guidelines
- **Goal**: ELIMINATE BUGS.
- **Refs** (Filament v4 docs are acceptable for v5 testing APIs):
  - [Overview](https://filamentphp.com/docs/4.x/testing/overview)
  - [Resources](https://filamentphp.com/docs/4.x/testing/testing-resources)
  - [Tables](https://filamentphp.com/docs/4.x/testing/testing-tables)
  - [Schemas](https://filamentphp.com/docs/4.x/testing/testing-schemas)
  - [Actions](https://filamentphp.com/docs/4.x/testing/testing-actions)
  - [Notifications](https://filamentphp.com/docs/4.x/testing/testing-notifications)

## Execution
- **Do not run everything**. Run tests per package/scope.
- **Single**: `./vendor/bin/pest --parallel path/to/Test.php`
- **Dir**: `./vendor/bin/pest --parallel path/to/dir`
- **Full**: `./vendor/bin/pest --parallel ...` (final only)

## Coverage
- Always include `--parallel` when using `--coverage`.
- Command: `./vendor/bin/pest --coverage --parallel`
- Don’t run full coverage if `0% files > 10%`.
- Targets: Core ≥80%, Filament ≥70%, Support ≥80%.
- **Output**: ALWAYS pipe: `2>&1 | tee /tmp/out.txt`.


=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.16

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
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
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

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
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.
</laravel-boost-guidelines>
