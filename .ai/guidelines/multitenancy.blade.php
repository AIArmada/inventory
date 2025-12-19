# Multitenancy Guidelines

## Monorepo Contract
- **Boundary is mandatory**: Every package that stores tenant-owned data MUST define an explicit tenant boundary and enforce it on **every** read/write path.
- **Single source of truth**: Multi-tenancy primitives live in `commerce-support`.
- **No UI trust**: Filament form options are not security. Always validate on the server.
- **Column semantics**: `owner_type/owner_id` is reserved for the tenant boundary owner (rename any non-tenant uses).

## Data Model (Required)
- **Migration**: `$table->nullableMorphs('owner')` for tenant-owned tables.
- **Model**: `use HasOwner` (from `commerce-support`).
- **Provider**: Bind `OwnerResolverInterface` to resolve current owner context.
- **Non-tenant "ownership"** (wallet holder, payee, actor, etc.) MUST use different column names/relationships.

## Enforcement (Default-on)
- Tenant-owned `HasOwner` models SHOULD be protected by commerce-support's default-on owner global scope when owner mode is enabled.
- **Escape hatch**: intentionally cross-tenant/system operations MUST use an explicit, greppable opt-out (e.g. `->withoutOwnerScope()` / `withoutGlobalScope(OwnerScope::class)`).

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
- Route-model binding/download routes MUST NOT resolve cross-tenant rows for `HasOwner` models; use hardened binding patterns (service provider binding) where applicable.

## Non-UI Surfaces
- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks MUST apply the same owner scoping as HTTP/Filament.
- Jobs/commands MUST NOT rely on ambient web auth to resolve owner; pass/iterate owner explicitly and apply owner context via the standard override mechanism.

## Verification (Required)
- Add at least one cross-tenant regression test per package proving:
	- Cross-tenant reads return empty/404.
	- Cross-tenant writes throw/abort.
- Grep for unscoped entrypoints:
	- `rg -n -- "::query\(|->query\(|getEloquentQuery\(" packages/<pkg>/src`
	- `rg -n -- "count\(|sum\(|avg\(|exists\(" packages/<pkg>/src`
	- `rg -n -- "DB::table\(" packages/<pkg>/src`
	- `rg -n -- "Route::.*\{.*\}" packages/<pkg>/routes`
	- `rg -n -- "withoutOwnerScope\(|withoutGlobalScope\(.*Owner" packages/<pkg>/src`
