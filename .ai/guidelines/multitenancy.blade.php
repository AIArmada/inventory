# Multitenancy Guidelines

## Monorepo Contract
- **Boundary is mandatory**: Every package that stores tenant-owned data MUST define an explicit tenant boundary and enforce it on **every** read/write path.
- **Single source of truth**: Multi-tenancy primitives live in `commerce-support`.
- **No UI trust**: Filament form options are not security. Always validate on the server.

## Data Model (Required)
- **Migration**: `$table->nullableMorphs('owner')` for tenant-owned tables.
- **Model**: `use HasOwner` (from `commerce-support`).
- **Provider**: Bind `OwnerResolverInterface` to resolve current owner context.

## Query Rules (Non-negotiable)
- **Reads**: Must be scoped via owner context (e.g., `Model::forOwner($owner)`), including counts, aggregates, exports, widgets, and health checks.
- **Writes**: Any inbound foreign IDs (e.g., `location_id`, `order_id`, `batch_id`) MUST be validated as belonging to the current owner scope before attach/update.
- **Global rows**: If a package supports global rows, provide clear semantics:
	- `Model::forOwner($owner)` (owner-only)
	- `Model::globalOnly()` (global-only)
	- Optional: include-global behavior must be explicit and consistent.

## Filament Rules
- **Resources**: Override `getEloquentQuery()` and apply owner scoping there (not only in filters).
- **Actions**: Validate IDs again inside `->action()` handlers (defense-in-depth).
- **Relationship selects**: Scope option queries and validate submitted IDs.

## Non-UI Surfaces
- Console commands, queued jobs, scheduled tasks, exports, reports, health checks, and webhooks MUST apply the same owner scoping as HTTP/Filament.

## Verification (Required)
- Add at least one cross-tenant regression test per package proving:
	- Cross-tenant reads return empty/404.
	- Cross-tenant writes throw/abort.
- Grep for unscoped entrypoints:
	- `rg -- "::query\(|->query\(|->getEloquentQuery\(" packages/<pkg>/src`