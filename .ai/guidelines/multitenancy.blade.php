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