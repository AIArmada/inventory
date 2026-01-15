---
title: Settings Page
---

# Settings Page

The plugin includes a settings page for configuring global tax behavior without code changes.

## Enabling the Settings Page

```php
FilamentTaxPlugin::make()
    ->settings(true);
```

Or via configuration:

```php
// config/filament-tax.php
'features' => [
    'settings' => true,
],
```

## Navigation

The settings page appears at:
- **Path:** `/admin/tax/settings`
- **Navigation:** Tax > Tax Settings
- **Icon:** `heroicon-o-cog`

## Settings Overview

The page manages the `TaxSettings` class from the base tax package using Spatie Laravel Settings.

```
┌─────────────────────────────────────────────────────────────┐
│ Tax Settings                                                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ General Settings                                             │
│ ─────────────────────────────────────────────────────────── │
│                                                              │
│ ☑ Enable Tax Calculation                                    │
│   When enabled, taxes are calculated on orders               │
│                                                              │
│ Default Tax Rate (%)   [6.00___________]                    │
│   Used when no zone-specific rate is found                   │
│                                                              │
│ Rounding Precision     [2___]                               │
│   Decimal places for tax amounts                             │
│                                                              │
│ ─────────────────────────────────────────────────────────── │
│ Price Configuration                                          │
│ ─────────────────────────────────────────────────────────── │
│                                                              │
│ ☐ Prices Include Tax                                        │
│   Enable if product prices already include tax               │
│                                                              │
│ ☐ Display Prices with Tax                                   │
│   Show tax-inclusive prices to customers                     │
│                                                              │
│ ─────────────────────────────────────────────────────────── │
│ Shipping & Exemptions                                        │
│ ─────────────────────────────────────────────────────────── │
│                                                              │
│ ☑ Shipping is Taxable                                       │
│   Apply tax to shipping charges                              │
│                                                              │
│ ☑ Enable Tax Exemptions                                     │
│   Allow customers to apply for tax exemptions                │
│                                                              │
│                                   [Cancel]  [Save Settings]  │
└─────────────────────────────────────────────────────────────┘
```

## Available Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `enabled` | bool | `true` | Master switch for tax calculation |
| `defaultTaxRate` | int | `0` | Fallback rate in basis points |
| `roundingPrecision` | int | `2` | Decimal places for rounding |
| `pricesIncludeTax` | bool | `false` | Whether catalog prices include tax |
| `displayWithTax` | bool | `false` | Show tax-inclusive prices to customers |
| `shippingTaxable` | bool | `true` | Apply tax to shipping |
| `exemptionsEnabled` | bool | `true` | Allow tax exemptions |

## Implementation

The settings page extends Filament's settings page with custom form fields:

```php
namespace AIArmada\FilamentTax\Pages;

use AIArmada\Tax\Settings\TaxSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageTaxSettings extends SettingsPage
{
    protected static string $settings = TaxSettings::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    
    protected static ?string $navigationGroup = 'Tax';
    
    protected static ?string $title = 'Tax Settings';
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Settings')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enable Tax Calculation')
                            ->helperText('When enabled, taxes are calculated on orders'),
                            
                        TextInput::make('defaultTaxRate')
                            ->label('Default Tax Rate (%)')
                            ->numeric()
                            ->suffix('%')
                            ->helperText('Used when no zone-specific rate is found'),
                            
                        TextInput::make('roundingPrecision')
                            ->label('Rounding Precision')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(8)
                            ->helperText('Decimal places for tax amounts'),
                    ]),
                    
                Section::make('Price Configuration')
                    ->schema([
                        Toggle::make('pricesIncludeTax')
                            ->label('Prices Include Tax')
                            ->helperText('Enable if product prices already include tax'),
                            
                        Toggle::make('displayWithTax')
                            ->label('Display Prices with Tax')
                            ->helperText('Show tax-inclusive prices to customers'),
                    ]),
                    
                Section::make('Shipping & Exemptions')
                    ->schema([
                        Toggle::make('shippingTaxable')
                            ->label('Shipping is Taxable')
                            ->helperText('Apply tax to shipping charges'),
                            
                        Toggle::make('exemptionsEnabled')
                            ->label('Enable Tax Exemptions')
                            ->helperText('Allow customers to apply for tax exemptions'),
                    ]),
            ]);
    }
}
```

## Blade View

The page uses a custom Blade view at `resources/views/pages/manage-tax-settings.blade.php`:

```blade
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
        
        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
```

## Authorization

The settings page requires proper authorization:

### With filament-authz

```php
// Permissions checked:
// - tax.settings.view (to see page)
// - tax.settings.update (to save changes)
```

### Without filament-authz

Create a gate or use policies:

```php
// AuthServiceProvider
Gate::define('manage-tax-settings', function ($user) {
    return $user->hasRole('admin');
});
```

Then in the page:

```php
public static function canAccess(): bool
{
    return Gate::allows('manage-tax-settings');
}
```

## Extending Settings

To add more settings fields:

### 1. Extend the Settings Class

```php
namespace App\Settings;

use AIArmada\Tax\Settings\TaxSettings as BaseTaxSettings;

class TaxSettings extends BaseTaxSettings
{
    public bool $autoDetectZone = true;
    public string $fallbackZoneId = '';
    
    public static function group(): string
    {
        return 'tax';
    }
}
```

### 2. Create Migration

```bash
php artisan make:settings-migration AddAutoDetectToTaxSettings
```

```php
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class AddAutoDetectToTaxSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tax.autoDetectZone', true);
        $this->migrator->add('tax.fallbackZoneId', '');
    }
}
```

### 3. Create Custom Settings Page

```php
namespace App\Filament\Pages;

use AIArmada\FilamentTax\Pages\ManageTaxSettings as BaseSettingsPage;
use App\Settings\TaxSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class ManageTaxSettings extends BaseSettingsPage
{
    protected static string $settings = TaxSettings::class;
    
    public function form(Form $form): Form
    {
        return $form->schema([
            ...parent::form($form)->getComponents(),
            
            Section::make('Zone Detection')
                ->schema([
                    Toggle::make('autoDetectZone')
                        ->label('Auto-detect Tax Zone')
                        ->helperText('Automatically detect zone from customer address'),
                        
                    Select::make('fallbackZoneId')
                        ->label('Fallback Zone')
                        ->options(TaxZone::pluck('name', 'id'))
                        ->helperText('Zone to use when detection fails'),
                ]),
        ]);
    }
}
```

### 4. Register in Panel

```php
use App\Filament\Pages\ManageTaxSettings;

public function panel(Panel $panel): Panel
{
    return $panel
        ->pages([
            ManageTaxSettings::class,
        ])
        ->plugins([
            FilamentTaxPlugin::make()
                ->settings(false), // Disable built-in page
        ]);
}
```

## Reading Settings in Code

Access settings anywhere in your application:

```php
use AIArmada\Tax\Settings\TaxSettings;

$settings = app(TaxSettings::class);

if ($settings->enabled) {
    // Tax is enabled
}

if ($settings->pricesIncludeTax) {
    // Extract tax from price
} else {
    // Add tax to price
}

if ($settings->shippingTaxable) {
    // Calculate tax on shipping
}
```

## Settings Caching

Spatie Laravel Settings caches settings by default. Clear cache after changing settings programmatically:

```bash
php artisan cache:clear
```

Or in code:

```php
use Spatie\LaravelSettings\SettingsCache;

app(SettingsCache::class)->clear();
```
