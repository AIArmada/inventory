---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Filament Tax plugin.

## Installation Issues

### Plugin Not Loading

**Symptom:** Tax resources don't appear in navigation.

**Solutions:**

1. Verify plugin is registered in panel:
   ```php
   public function panel(Panel $panel): Panel
   {
       return $panel
           ->plugins([
               FilamentTaxPlugin::make(),
           ]);
   }
   ```

2. Clear caches:
   ```bash
   php artisan filament:clear-cached-components
   php artisan config:clear
   php artisan cache:clear
   ```

3. Check features are enabled:
   ```php
   FilamentTaxPlugin::make()
       ->zones(true)
       ->classes(true)
       ->rates(true)
       ->exemptions(true);
   ```

### Missing Views

**Symptom:** "View not found" errors.

**Solutions:**

1. Publish views:
   ```bash
   php artisan vendor:publish --tag=filament-tax-views
   ```

2. Clear view cache:
   ```bash
   php artisan view:clear
   ```

### Migration Errors

**Symptom:** "Table doesn't exist" errors.

**Solutions:**

1. Run base package migrations:
   ```bash
   php artisan vendor:publish --tag=tax-migrations
   php artisan migrate
   ```

2. For settings tables:
   ```bash
   php artisan vendor:publish --tag=tax-settings
   php artisan migrate
   ```

## Resource Issues

### Resources Show "0 Records"

**Symptom:** Tables are empty despite having data.

**Checklist:**

1. **Owner scoping active?**
   ```php
   // Check config
   config('tax.features.owner.enabled');
   
   // If enabled, verify owner context
   use AIArmada\CommerceSupport\Support\OwnerContext;
   OwnerContext::resolve(); // Should return current tenant
   ```

2. **Check active filters:**
   - Resources may filter to active records only by default
   - Clear all filters and search

3. **Database has records:**
   ```php
   TaxZone::withoutGlobalScopes()->count();
   ```

### Forms Not Saving

**Symptom:** Submit succeeds but changes aren't persisted.

**Checklist:**

1. **Fillable attributes:**
   ```php
   // Model must have correct fillable
   protected $fillable = ['name', 'code', 'countries', ...];
   ```

2. **Validation errors:**
   - Check browser console for JavaScript errors
   - Check Laravel log for validation failures

3. **Field names match model:**
   - Form field names must match database columns

### Relation Manager Errors

**Symptom:** "Method does not exist" on relation manager.

**Solutions:**

1. Ensure relationship exists on model:
   ```php
   // TaxZone model
   public function rates(): HasMany
   {
       return $this->hasMany(TaxRate::class, 'zone_id');
   }
   ```

2. Check relationship name matches:
   ```php
   class RatesRelationManager extends RelationManager
   {
       protected static string $relationship = 'rates'; // Must match model method
   }
   ```

## Widget Issues

### Widgets Not Appearing

**Symptom:** Dashboard shows no tax widgets.

**Solutions:**

1. Enable widgets in plugin:
   ```php
   FilamentTaxPlugin::make()->widgets(true);
   ```

2. Check widget authorization:
   ```php
   class TaxStatsWidget extends Widget
   {
       public static function canView(): bool
       {
           return auth()->user()->can('view', TaxZone::class);
       }
   }
   ```

3. Widgets only show on dashboard:
   - Navigate to `/admin` (or your panel path)
   - Not visible on resource pages

### Widget Data Incorrect

**Symptom:** Stats show wrong numbers.

**Checklist:**

1. **Owner scoping:**
   - Widgets query with global scopes
   - Verify tenant context is correct

2. **Active vs all:**
   - Widgets may only count active records
   - Check widget query logic

3. **Cache stale:**
   ```bash
   php artisan cache:clear
   ```

### Zone Coverage Widget Empty

**Symptom:** Zone coverage shows no zones.

**Solutions:**

1. Create at least one zone:
   ```php
   TaxZone::factory()->forMalaysia()->create();
   ```

2. Check widget query:
   ```php
   // Widget fetches with owner scope
   TaxZone::query()
       ->with('rates')
       ->orderBy('priority', 'desc')
       ->get();
   ```

## Settings Page Issues

### Settings Not Saving

**Symptom:** Settings save but revert on refresh.

**Solutions:**

1. Run settings migration:
   ```bash
   php artisan vendor:publish --tag=tax-settings
   php artisan migrate
   ```

2. Check settings table exists:
   ```sql
   SELECT * FROM settings WHERE group = 'tax';
   ```

3. Clear settings cache:
   ```bash
   php artisan cache:clear
   ```

### Settings Page Not Found

**Symptom:** 404 on settings URL.

**Solutions:**

1. Enable settings in plugin:
   ```php
   FilamentTaxPlugin::make()->settings(true);
   ```

2. Check route registration:
   ```bash
   php artisan route:list --name=tax
   ```

## Authorization Issues

### "Unauthorized" Errors

**Symptom:** 403 errors when accessing resources.

**Solutions:**

1. **With filament-authz:**
   ```php
   // Verify permissions exist
   // tax.zones.view, tax.zones.create, etc.
   ```

2. **With policies:**
   ```bash
   php artisan make:policy TaxZonePolicy --model=TaxZone
   ```

3. **Check user permissions:**
   ```php
   auth()->user()->can('viewAny', TaxZone::class);
   ```

### Actions Not Visible

**Symptom:** Create/Edit/Delete buttons missing.

**Solutions:**

1. Check authorization methods:
   ```php
   TaxZoneResource::canCreate(); // Should return true
   TaxZoneResource::canEdit($record); // Should return true
   TaxZoneResource::canDelete($record); // Should return true
   ```

2. Verify user has permissions:
   ```php
   $user->hasPermission('tax.zones.create');
   ```

## Performance Issues

### Slow Page Load

**Symptom:** Resources take long to load.

**Solutions:**

1. Add database indexes (included in migrations):
   ```sql
   CREATE INDEX idx_tax_zones_active ON tax_zones(is_active);
   CREATE INDEX idx_tax_rates_zone ON tax_rates(zone_id);
   ```

2. Reduce related queries:
   ```php
   // In resource
   public static function getEloquentQuery(): Builder
   {
       return parent::getEloquentQuery()->with(['rates']);
   }
   ```

3. Disable table polling:
   ```php
   // config/filament-tax.php
   'tables' => [
       'polling' => false,
   ],
   ```

### Widget Queries Slow

**Symptom:** Dashboard takes long to load.

**Solutions:**

1. Cache widget data:
   ```php
   protected function getStats(): array
   {
       return Cache::remember('tax-stats', 300, fn () => [
           Stat::make('Zones', TaxZone::active()->count()),
           // ...
       ]);
   }
   ```

2. Lazy load widgets:
   ```php
   protected static bool $isLazy = true;
   ```

## Multi-Panel Issues

### Resources on Wrong Panel

**Symptom:** Tax resources appear on panels where they shouldn't.

**Solutions:**

1. Register plugin per-panel:
   ```php
   // AdminPanel - full access
   FilamentTaxPlugin::make();
   
   // StaffPanel - limited
   FilamentTaxPlugin::make()
       ->settings(false)
       ->widgets(false);
   
   // CustomerPanel - none
   // Don't register plugin
   ```

2. Check panel ID:
   ```php
   // In resource
   public static function shouldRegisterNavigation(): bool
   {
       return filament()->getCurrentPanel()->getId() === 'admin';
   }
   ```

## Debugging Tips

### Enable Query Log

```php
DB::enableQueryLog();

// ... perform actions ...

dd(DB::getQueryLog());
```

### Check Component Registration

```bash
php artisan filament:list-components
```

### Inspect Livewire State

Use browser dev tools:
1. Install Livewire DevTools extension
2. Inspect component state in Elements tab

### Log Authorization Checks

```php
// In resource
public static function canViewAny(): bool
{
    $result = parent::canViewAny();
    Log::debug('TaxZoneResource::canViewAny', ['result' => $result]);
    return $result;
}
```

## Getting Help

1. **Check Laravel logs:** `storage/logs/laravel.log`
2. **Enable debug mode:** `APP_DEBUG=true` in `.env`
3. **Check Filament version:** `composer show filament/filament`
4. **Verify dependencies:** `composer show aiarmada/tax`

When reporting issues, include:
- Laravel version
- Filament version
- Package version
- Error message/stack trace
- Steps to reproduce
