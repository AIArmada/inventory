<laravel-boost-guidelines>
=== .ai/filament rules ===

# Filament Guidelines
- **Ver**: Filament v5 API Mandatory.
- **Spatie**: MUST use official Filament plugins (Tags, Settings, Media, Fonts).
- **Actions**: Use built-in `Import`/`Export` actions only.
- **Verification**: Verify all signatures against v5 docs.


=== .ai/multitenancy rules ===

# Multitenancy Guidelines
- **Pkg**: `commerce-support`.
- **Impl**:
- Mig: `$table->nullableMorphs('owner')`.
- Model: `use HasOwner`.
- Provider: Bind `OwnerResolverInterface`.
- **Usage**:
- Owner: `Model::forOwner($owner)->get()`.
- Global: `Model::globalOnly()->get()`.


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


=== .ai/docs rules ===

# Documentation Guidelines
- **Loc**: `packages/<pkg>/docs/`.
  - **Files**: `01-overview`, `02-install`, `03-config`, `04-usage`, `99-trouble`.
  - **Fmt**: Markdown + YAML Frontmatter (`title:`).

  ## Features
  - **Components**: Use `import Aside from "@components/Aside.astro"`.
  - **Variants**: `info`, `warning`, `tip`, `danger`.
  - **Content**: Copy-paste ready code examples. `##` headers. Explains breaking changes.


=== .ai/phpstan rules ===

# PHPStan Guidelines
- **Lvl**: 6.
- **Scope**: Per package (`packages/pkg/src`).
- **Rules**:
- Respect `phpstan.neon`.
- NO new `ignoreErrors` unless exhausted.
- Fix root causes over suppression.


=== .ai/packages rules ===

# Packages Guidelines
- **Indep**: Must run standalone. `suggest` over `require`.
- **Integ**: Auto-enable via `class_exists()` check in `boot()`.
- **Code**: All DTOs via `spatie/laravel-data`.
- **Test**: Verify standalone install and integration.


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
