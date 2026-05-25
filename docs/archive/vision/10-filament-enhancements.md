# Filament Enhancements

> **Document:** 10 of 11  
> **Package:** `aiarmada/filament-inventory`  
> **Status:** Vision

---

## Overview

Transform the Filament Inventory panel into a **comprehensive warehouse management dashboard** with batch/serial tracking, cost visibility, replenishment management, and real-time analytics.

---

## Dashboard Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                  INVENTORY DASHBOARD                          │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐            │
│  │ Total   │ │  Low    │ │ Expiring│ │Inventory│            │
│  │ SKUs    │ │  Stock  │ │ Soon    │ │ Value   │            │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘            │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │           Stock Level Overview (Chart)                  │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                               │
│  ┌───────────────────────┐  ┌───────────────────────────┐   │
│  │ Low Stock Alerts      │  │ Reorder Suggestions       │   │
│  │ (Critical First)      │  │ (Pending Approval)        │   │
│  └───────────────────────┘  └───────────────────────────┘   │
│                                                               │
│  ┌───────────────────────┐  ┌───────────────────────────┐   │
│  │ Expiring Batches      │  │ Recent Movements          │   │
│  └───────────────────────┘  └───────────────────────────┘   │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### InventoryOverviewWidget

```php
class InventoryOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '60s';
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total SKUs', $this->getSkuCount())
                ->description("{$this->getSkusWithStock()} with stock")
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
            
            Stat::make('Low Stock Items', $this->getLowStockCount())
                ->description("{$this->getCriticalCount()} critical")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->url(InventoryLevelResource::getUrl('index', [
                    'tableFilters[low_stock][value]' => true,
                ])),
            
            Stat::make('Expiring Soon', $this->getExpiringCount())
                ->description('Next 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->url(BatchResource::getUrl('index', [
                    'tableFilters[expiring_soon][value]' => true,
                ])),
            
            Stat::make('Inventory Value', $this->getInventoryValue())
                ->description($this->getValueChange() . ' vs last month')
                ->descriptionIcon($this->getValueChange() >= 0 
                    ? 'heroicon-m-arrow-trending-up' 
                    : 'heroicon-m-arrow-trending-down')
                ->color('success')
                ->chart($this->getValueTrend()),
        ];
    }

    private function getInventoryValue(): string
    {
        $value = app(InventoryValuationService::class)
            ->getCurrentValue()
            ->totalValueMinor;
        
        return Money::format($value);
    }
}
```

### LowStockAlertsWidget

```php
class LowStockAlertsWidget extends Widget
{
    protected static string $view = 'filament-inventory::widgets.low-stock-alerts';
    protected int|string|array $columnSpan = 1;
    protected static ?string $pollingInterval = '120s';
    
    public function getItems(): Collection
    {
        return InventoryLevel::query()
            ->with(['inventoryable', 'location'])
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_point')
            ->orderByRaw('(quantity_on_hand - quantity_reserved) - reorder_point ASC')
            ->limit(10)
            ->get()
            ->map(fn ($level) => [
                'name' => $level->inventoryable->name ?? 'Unknown',
                'location' => $level->location->name,
                'available' => $level->available,
                'reorder_point' => $level->reorder_point,
                'deficit' => $level->reorder_point - $level->available,
                'status' => $this->getStatus($level),
            ]);
    }

    private function getStatus(InventoryLevel $level): string
    {
        if ($level->available <= 0) {
            return 'out_of_stock';
        }
        if ($level->available <= $level->safety_stock) {
            return 'critical';
        }
        return 'low';
    }
}
```

### ReorderSuggestionsWidget

```php
class ReorderSuggestionsWidget extends Widget
{
    protected static string $view = 'filament-inventory::widgets.reorder-suggestions';
    protected int|string|array $columnSpan = 1;
    
    public function getSuggestions(): Collection
    {
        return InventoryReorderSuggestion::query()
            ->where('status', 'pending')
            ->with(['inventoryable', 'location'])
            ->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")
            ->limit(10)
            ->get();
    }

    public function approveSuggestion(string $id): void
    {
        $suggestion = InventoryReorderSuggestion::find($id);
        $suggestion->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);
        
        Notification::make()
            ->title('Reorder Approved')
            ->success()
            ->send();
    }
}
```

### ExpiringBatchesWidget

```php
class ExpiringBatchesWidget extends Widget
{
    protected static string $view = 'filament-inventory::widgets.expiring-batches';
    protected int|string|array $columnSpan = 1;
    
    public function getBatches(): Collection
    {
        return InventoryBatch::query()
            ->where('status', BatchStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->where('quantity_on_hand', '>', 0)
            ->with(['inventoryable', 'location'])
            ->orderBy('expires_at')
            ->limit(10)
            ->get()
            ->map(fn ($batch) => [
                'batch_number' => $batch->batch_number,
                'product' => $batch->inventoryable->name,
                'location' => $batch->location->name,
                'quantity' => $batch->quantity_on_hand,
                'expires_at' => $batch->expires_at,
                'days_until' => $batch->daysUntilExpiry(),
            ]);
    }
}
```

---

## Location Resource

### InventoryLocationResource

```php
class InventoryLocationResource extends Resource
{
    protected static ?string $model = InventoryLocation::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Inventory';
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Location Details')
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Select::make('type')
                            ->options(LocationType::class)
                            ->required()
                            ->live(),
                        
                        Select::make('parent_id')
                            ->label('Parent Location')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->visible(fn ($get) => LocationType::tryFrom($get('type'))?->isContainer() === false),
                    ])
                    ->columns(2),
                
                Section::make('Capacity')
                    ->schema([
                        TextInput::make('max_capacity')
                            ->numeric(),
                        
                        Select::make('capacity_unit')
                            ->options([
                                'units' => 'Units',
                                'cubic_m' => 'Cubic Meters',
                                'kg' => 'Kilograms',
                            ])
                            ->default('units'),
                        
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3),
                
                Section::make('Attributes')
                    ->schema([
                        Select::make('temperature_zone')
                            ->options(TemperatureZone::class),
                        
                        Toggle::make('is_pickable')
                            ->default(true),
                        
                        Toggle::make('is_receivable')
                            ->default(true),
                        
                        Toggle::make('is_hazmat_certified'),
                        
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(3),
                
                Section::make('Coordinates')
                    ->schema([
                        TextInput::make('coordinate_x')
                            ->numeric(),
                        TextInput::make('coordinate_y')
                            ->numeric(),
                        TextInput::make('coordinate_z')
                            ->numeric(),
                        TextInput::make('pick_sequence'),
                    ])
                    ->columns(4)
                    ->collapsed(),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('name')
                    ->searchable(),
                
                TextColumn::make('type')
                    ->badge(),
                
                TextColumn::make('parent.name')
                    ->label('Parent'),
                
                TextColumn::make('inventoryLevels_count')
                    ->counts('inventoryLevels')
                    ->label('SKUs'),
                
                TextColumn::make('utilization')
                    ->label('Utilization')
                    ->state(fn ($record) => $record->max_capacity 
                        ? round(($record->current_utilization / $record->max_capacity) * 100) . '%'
                        : '-'),
                
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(LocationType::class),
                
                Filter::make('is_active')
                    ->toggle(),
            ])
            ->actions([
                Action::make('view_stock')
                    ->icon('heroicon-o-cube')
                    ->url(fn ($record) => InventoryLevelResource::getUrl('index', [
                        'tableFilters[location_id][value]' => $record->id,
                    ])),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ChildLocationsRelationManager::class,
            InventoryLevelsRelationManager::class,
        ];
    }
}
```

---

## Batch Resource

### BatchResource

```php
class BatchResource extends Resource
{
    protected static ?string $model = InventoryBatch::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Inventory';
    
    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()
            ->where('status', BatchStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->searchable()
                    ->copyable(),
                
                TextColumn::make('inventoryable.name')
                    ->label('Product')
                    ->searchable(),
                
                TextColumn::make('location.name')
                    ->label('Location'),
                
                TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->sortable(),
                
                TextColumn::make('quantity_available')
                    ->label('Available'),
                
                TextColumn::make('expires_at')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => 
                        $record->isExpired() ? 'danger' : 
                        ($record->isExpiringSoon() ? 'warning' : null)),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BatchStatus $state) => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BatchStatus::class),
                
                Filter::make('expiring_soon')
                    ->label('Expiring Soon (30 days)')
                    ->query(fn ($query) => $query
                        ->whereNotNull('expires_at')
                        ->whereBetween('expires_at', [now(), now()->addDays(30)])),
                
                Filter::make('expired')
                    ->query(fn ($query) => $query
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<', now())),
            ])
            ->actions([
                Action::make('dispose')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => 
                        app(BatchService::class)->dispose($record, $data['reason'])),
            ]);
    }
}
```

---

## Serial Resource

### SerialResource

```php
class SerialResource extends Resource
{
    protected static ?string $model = InventorySerial::class;
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationGroup = 'Inventory';
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')
                    ->searchable()
                    ->copyable(),
                
                TextColumn::make('inventoryable.name')
                    ->label('Product')
                    ->searchable(),
                
                TextColumn::make('location.name'),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (SerialStatus $state) => $state->color()),
                
                TextColumn::make('condition')
                    ->badge(),
                
                TextColumn::make('warranty_ends_at')
                    ->date()
                    ->label('Warranty Until')
                    ->color(fn ($record) => $record->isUnderWarranty() ? 'success' : 'gray'),
                
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('In Stock'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SerialStatus::class),
                
                SelectFilter::make('condition')
                    ->options(SerialCondition::class),
                
                Filter::make('in_stock')
                    ->query(fn ($query) => $query->where('status', SerialStatus::InStock)),
                
                Filter::make('warranty_expiring')
                    ->label('Warranty Expiring (30 days)')
                    ->query(fn ($query) => $query
                        ->whereNotNull('warranty_ends_at')
                        ->whereBetween('warranty_ends_at', [now(), now()->addDays(30)])),
            ])
            ->actions([
                Action::make('view_history')
                    ->icon('heroicon-o-clock')
                    ->modalContent(fn ($record) => view('filament-inventory::modals.serial-history', [
                        'history' => $record->getLifecycle(),
                    ])),
            ]);
    }
}
```

---

## Quick Actions

### Receive Stock Action

```php
class ReceiveStockAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'receive_stock';
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Receive Stock')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->form([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('inventoryable', 'name')
                    ->searchable()
                    ->required(),
                
                Select::make('location_id')
                    ->label('Location')
                    ->options(InventoryLocation::where('is_receivable', true)->pluck('name', 'id'))
                    ->required(),
                
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                
                TextInput::make('unit_cost')
                    ->numeric()
                    ->prefix('RM'),
                
                Section::make('Batch Details')
                    ->schema([
                        TextInput::make('batch_number'),
                        DatePicker::make('expires_at'),
                        DatePicker::make('manufactured_at'),
                    ])
                    ->columns(3)
                    ->collapsed(),
            ])
            ->action(function (array $data) {
                app(InventoryService::class)->receive(
                    $data['product_id'],
                    $data['location_id'],
                    $data['quantity'],
                    [
                        'unit_cost_minor' => $data['unit_cost'] * 100,
                        'batch_number' => $data['batch_number'],
                        'expires_at' => $data['expires_at'],
                    ]
                );
                
                Notification::make()
                    ->title('Stock Received')
                    ->success()
                    ->send();
            });
    }
}
```

### Transfer Stock Action

```php
class TransferStockAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Transfer Stock')
            ->icon('heroicon-o-arrows-right-left')
            ->color('info')
            ->form([
                Select::make('from_location_id')
                    ->label('From Location')
                    ->options(InventoryLocation::pluck('name', 'id'))
                    ->required()
                    ->live(),
                
                Select::make('product_id')
                    ->label('Product')
                    ->options(function ($get) {
                        $locationId = $get('from_location_id');
                        if (! $locationId) {
                            return [];
                        }
                        
                        return InventoryLevel::where('location_id', $locationId)
                            ->where('quantity_on_hand', '>', 0)
                            ->with('inventoryable')
                            ->get()
                            ->pluck('inventoryable.name', 'inventoryable_id');
                    })
                    ->required(),
                
                Select::make('to_location_id')
                    ->label('To Location')
                    ->options(fn ($get) => InventoryLocation::where('id', '!=', $get('from_location_id'))
                        ->pluck('name', 'id'))
                    ->required(),
                
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                
                Textarea::make('notes'),
            ])
            ->action(function (array $data) {
                app(InventoryService::class)->transfer(
                    $data['product_id'],
                    $data['from_location_id'],
                    $data['to_location_id'],
                    $data['quantity'],
                    ['notes' => $data['notes']]
                );
                
                Notification::make()
                    ->title('Stock Transferred')
                    ->success()
                    ->send();
            });
    }
}
```

---

## Navigation Structure

```
Inventory
├── Dashboard
├── Locations
│   ├── All Locations
│   └── Location Tree
├── Stock Levels
├── Batches (badge: expiring)
├── Serial Numbers
├── Movements
└── Allocations

Replenishment
├── Reorder Suggestions (badge: pending)
├── Demand History
└── Supplier Lead Times

Analytics
├── Inventory Value
├── Stock Velocity
├── Expiry Report
└── Stockout Analysis

Settings
├── Allocation Strategies
├── Alert Thresholds
└── Costing Methods
```

---

## Navigation

**Previous:** [09-database-evolution.md](09-database-evolution.md)  
**Next:** [11-implementation-roadmap.md](11-implementation-roadmap.md)
