---
title: Customization
---

# Customization

This guide covers how to extend and customize the Filament Tax plugin.

## Extending Resources

### Override Resource Classes

Create your own resource extending the base:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentTax\Resources\TaxZoneResource as BaseResource;

class TaxZoneResource extends BaseResource
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    
    protected static ?string $navigationGroup = 'Store Settings';
    
    protected static ?int $navigationSort = 5;
}
```

Register in your panel:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            App\Filament\Resources\TaxZoneResource::class,
        ])
        ->plugins([
            FilamentTaxPlugin::make()
                ->zones(false), // Disable built-in
        ]);
}
```

### Custom Form Schema

Extend the form with additional fields:

```php
namespace App\Filament\Resources\TaxZoneResource\Schemas;

use AIArmada\FilamentTax\Resources\TaxZoneResource\Schemas\TaxZoneForm as BaseForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class TaxZoneForm extends BaseForm
{
    public static function make(): array
    {
        return array_merge(parent::make(), [
            Select::make('tax_authority')
                ->label('Tax Authority')
                ->options([
                    'federal' => 'Federal',
                    'state' => 'State',
                    'local' => 'Local',
                ]),
                
            TextInput::make('authority_id')
                ->label('Authority ID'),
        ]);
    }
}
```

### Custom Table Columns

Add columns to the table:

```php
namespace App\Filament\Resources\TaxZoneResource\Tables;

use AIArmada\FilamentTax\Resources\TaxZoneResource\Tables\TaxZonesTable as BaseTable;
use Filament\Tables\Columns\TextColumn;

class TaxZonesTable extends BaseTable
{
    public static function make(): array
    {
        return array_merge(parent::make(), [
            TextColumn::make('tax_authority')
                ->badge()
                ->colors([
                    'primary' => 'federal',
                    'success' => 'state',
                    'warning' => 'local',
                ]),
                
            TextColumn::make('total_collected')
                ->money('MYR')
                ->label('Total Collected'),
        ]);
    }
}
```

## Custom Actions

### Add Resource Actions

```php
namespace App\Filament\Resources\TaxZoneResource\Pages;

use AIArmada\FilamentTax\Resources\TaxZoneResource\Pages\ListTaxZones as BaseListPage;
use Filament\Actions;

class ListTaxZones extends BaseListPage
{
    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            Actions\Action::make('export')
                ->label('Export Zones')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportZones()),
                
            Actions\Action::make('import')
                ->label('Import Zones')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv']),
                ])
                ->action(fn (array $data) => $this->importZones($data)),
        ]);
    }
    
    protected function exportZones(): StreamedResponse
    {
        // Export implementation
    }
    
    protected function importZones(array $data): void
    {
        // Import implementation
    }
}
```

### Add Table Actions

```php
use Filament\Tables\Actions\Action;

public function table(Table $table): Table
{
    return $table
        ->actions([
            Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (TaxZone $record) {
                    $new = $record->replicate();
                    $new->name = $record->name . ' (Copy)';
                    $new->code = $record->code . '_COPY';
                    $new->is_default = false;
                    $new->save();
                    
                    // Copy rates too
                    foreach ($record->rates as $rate) {
                        $newRate = $rate->replicate();
                        $newRate->zone_id = $new->id;
                        $newRate->save();
                    }
                }),
                
            Action::make('test')
                ->icon('heroicon-o-beaker')
                ->form([
                    TextInput::make('country')->required(),
                    TextInput::make('state'),
                    TextInput::make('postcode'),
                ])
                ->action(function (TaxZone $record, array $data) {
                    $matches = $record->matchesAddress(
                        $data['country'],
                        $data['state'],
                        $data['postcode']
                    );
                    
                    Notification::make()
                        ->title($matches ? 'Zone matches!' : 'Zone does not match')
                        ->success()
                        ->send();
                }),
        ]);
}
```

## Custom Widgets

### Create New Widget

```php
namespace App\Filament\Widgets;

use AIArmada\Tax\Models\TaxRate;
use Filament\Widgets\ChartWidget;

class TaxRatesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Tax Rates by Zone';
    
    protected function getData(): array
    {
        $zones = TaxZone::with('rates')->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Number of Rates',
                    'data' => $zones->pluck('rates')->map->count()->toArray(),
                    'backgroundColor' => ['#f00', '#0f0', '#00f', '#ff0', '#f0f'],
                ],
            ],
            'labels' => $zones->pluck('name')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }
}
```

### Replace Built-in Widget

```php
// In a service provider
use Livewire\Livewire;

public function boot(): void
{
    Livewire::component(
        'filament-tax-stats-widget',
        App\Filament\Widgets\CustomTaxStatsWidget::class
    );
}
```

## Relation Managers

### Add to Existing Resource

```php
namespace App\Filament\Resources\TaxZoneResource;

use AIArmada\FilamentTax\Resources\TaxZoneResource as BaseResource;
use App\Filament\Resources\TaxZoneResource\RelationManagers\AuditLogsRelationManager;

class TaxZoneResource extends BaseResource
{
    public static function getRelations(): array
    {
        return array_merge(parent::getRelations(), [
            AuditLogsRelationManager::class,
        ]);
    }
}
```

### Create Custom Relation Manager

```php
namespace App\Filament\Resources\TaxZoneResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';
    
    protected static ?string $title = 'Audit Log';
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

## Custom Pages

### Add to Resource

```php
namespace App\Filament\Resources\TaxZoneResource\Pages;

use Filament\Resources\Pages\Page;

class ZoneAnalytics extends Page
{
    protected static string $resource = TaxZoneResource::class;
    
    protected static string $view = 'filament.pages.zone-analytics';
    
    public TaxZone $record;
    
    public function mount(TaxZone $record): void
    {
        $this->record = $record;
    }
    
    public function getTitle(): string
    {
        return 'Analytics: ' . $this->record->name;
    }
}
```

Register in resource:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListTaxZones::route('/'),
        'create' => Pages\CreateTaxZone::route('/create'),
        'edit' => Pages\EditTaxZone::route('/{record}/edit'),
        'view' => Pages\ViewTaxZone::route('/{record}'),
        'analytics' => Pages\ZoneAnalytics::route('/{record}/analytics'),
    ];
}
```

## Authorization Customization

### Custom Permission Names

```php
namespace App\Support;

use AIArmada\FilamentTax\Support\FilamentTaxAuthz;

class TaxAuthorization extends FilamentTaxAuthz
{
    protected static function getPermissionPrefix(): string
    {
        return 'commerce.tax'; // commerce.tax.zones.view instead of tax.zones.view
    }
}
```

### Gate-based Authorization

```php
// AuthServiceProvider
Gate::define('tax.zones.view', fn ($user) => $user->hasAnyRole(['admin', 'accountant']));
Gate::define('tax.zones.create', fn ($user) => $user->hasRole('admin'));
Gate::define('tax.zones.update', fn ($user) => $user->hasRole('admin'));
Gate::define('tax.zones.delete', fn ($user) => $user->hasRole('admin'));

// In resource
public static function canViewAny(): bool
{
    return Gate::allows('tax.zones.view');
}

public static function canCreate(): bool
{
    return Gate::allows('tax.zones.create');
}
```

## Views Customization

### Publish Views

```bash
php artisan vendor:publish --tag=filament-tax-views
```

This creates views in `resources/views/vendor/filament-tax/`:

```
resources/views/vendor/filament-tax/
├── pages/
│   └── manage-tax-settings.blade.php
└── widgets/
    └── zone-coverage.blade.php
```

### Override Specific View

Create a view at the same path to override:

```blade
{{-- resources/views/vendor/filament-tax/widgets/zone-coverage.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Tax Zone Coverage">
        {{-- Your custom implementation --}}
        <div class="grid grid-cols-3 gap-4">
            @foreach ($this->getZones() as $zone)
                <x-filament::card>
                    <h3 class="font-bold text-lg">{{ $zone->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $zone->code }}</p>
                    
                    <div class="mt-4">
                        <span class="text-2xl font-bold">{{ $zone->rates->count() }}</span>
                        <span class="text-sm text-gray-500">rates</span>
                    </div>
                </x-filament::card>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

## Localization

### Publish Translations

```bash
php artisan vendor:publish --tag=filament-tax-translations
```

### Override Translations

Edit `lang/vendor/filament-tax/en/messages.php`:

```php
return [
    'resources' => [
        'zone' => [
            'label' => 'Tax Region',
            'plural' => 'Tax Regions',
            'navigation_label' => 'Regions',
        ],
        'rate' => [
            'label' => 'Tax Percentage',
            'plural' => 'Tax Percentages',
        ],
    ],
    'fields' => [
        'rate' => 'Percentage',
        'is_compound' => 'Stacking Tax',
    ],
];
```

### Add New Language

Create `lang/vendor/filament-tax/ms/messages.php`:

```php
return [
    'resources' => [
        'zone' => [
            'label' => 'Zon Cukai',
            'plural' => 'Zon Cukai',
        ],
        'rate' => [
            'label' => 'Kadar Cukai',
            'plural' => 'Kadar Cukai',
        ],
    ],
];
```

## Multi-Tenancy

### Owner Scoping

The plugin respects the base tax package's owner scoping:

```php
// config/tax.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => true,
    ],
],
```

### Per-Panel Tenant Context

```php
FilamentTaxPlugin::make()
    ->modifyResourceQuery(function ($query) {
        // Custom scoping per panel
        if (filament()->getCurrentPanel()->getId() === 'store') {
            return $query->where('owner_id', filament()->getTenant()->id);
        }
        return $query;
    });
```

## Event Hooks

### Listen to Resource Events

```php
// EventServiceProvider
use AIArmada\Tax\Models\TaxZone;

TaxZone::created(function (TaxZone $zone) {
    Log::info('Tax zone created', ['zone' => $zone->toArray()]);
    
    // Create default rate for new zone
    if ($zone->is_default) {
        $zone->rates()->create([
            'name' => 'Default Rate',
            'tax_class' => 'standard',
            'rate' => config('tax.defaults.default_tax_rate'),
            'is_active' => true,
        ]);
    }
});

TaxZone::updated(function (TaxZone $zone) {
    // Clear cache when zone changes
    Cache::forget("tax-zone:{$zone->id}");
});
```

### Filament Lifecycle Hooks

```php
// In resource page
protected function afterCreate(): void
{
    Notification::make()
        ->title('Tax zone created')
        ->success()
        ->send();
        
    activity()
        ->causedBy(auth()->user())
        ->performedOn($this->record)
        ->log('Created tax zone');
}

protected function afterSave(): void
{
    // Clear related caches
    Cache::tags(['tax'])->flush();
}
```
