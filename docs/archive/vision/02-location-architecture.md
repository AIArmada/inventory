# Location Architecture

> **Document:** 02 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Evolve the flat location model into a **hierarchical warehouse architecture** supporting regions, warehouses, zones, aisles, racks, and bins for enterprise-scale warehouse management.

---

## Location Hierarchy

```
Organization
└── Region (Asia Pacific)
    └── Warehouse (KL-CENTRAL)
        ├── Zone (RECEIVING)
        │   └── Staging Area
        ├── Zone (PICKING)
        │   ├── Aisle A
        │   │   ├── Rack 01
        │   │   │   ├── Shelf 1
        │   │   │   │   ├── Bin A-01-1-A
        │   │   │   │   └── Bin A-01-1-B
        │   │   │   └── Shelf 2
        │   │   └── Rack 02
        │   └── Aisle B
        ├── Zone (PACKING)
        └── Zone (SHIPPING)
            └── Dock 1, Dock 2, Dock 3
```

---

## Location Types

### LocationType Enum

```php
enum LocationType: string
{
    case Region = 'region';
    case Warehouse = 'warehouse';
    case Store = 'store';
    case FulfillmentCenter = 'fulfillment_center';
    case Dropship = 'dropship';
    case Virtual = 'virtual';
    case Zone = 'zone';
    case Aisle = 'aisle';
    case Rack = 'rack';
    case Shelf = 'shelf';
    case Bin = 'bin';
    
    public function canHoldInventory(): bool
    {
        return match ($this) {
            self::Bin, self::Shelf, self::Zone, self::Warehouse, self::Store => true,
            default => false,
        };
    }
    
    public function isContainer(): bool
    {
        return match ($this) {
            self::Region, self::Warehouse, self::FulfillmentCenter, self::Zone, self::Aisle, self::Rack => true,
            default => false,
        };
    }
    
    public function getAllowedChildren(): array
    {
        return match ($this) {
            self::Region => [self::Warehouse, self::FulfillmentCenter, self::Store],
            self::Warehouse, self::FulfillmentCenter => [self::Zone],
            self::Zone => [self::Aisle, self::Bin, self::Rack],
            self::Aisle => [self::Rack],
            self::Rack => [self::Shelf],
            self::Shelf => [self::Bin],
            default => [],
        };
    }
}
```

---

## Enhanced Location Model

### InventoryLocation Schema

```php
Schema::table('inventory_locations', function (Blueprint $table) {
    // Hierarchy
    $table->foreignUuid('parent_id')->nullable();
    $table->string('path')->nullable()->index(); // Materialized path: /region/warehouse/zone
    $table->integer('depth')->default(0);
    
    // Type
    $table->string('type')->default('warehouse');
    
    // Capacity
    $table->integer('max_capacity')->nullable();
    $table->integer('current_utilization')->default(0);
    $table->string('capacity_unit')->default('units'); // units, cubic_m, kg
    
    // Picking
    $table->string('pick_sequence')->nullable(); // For pick path optimization
    $table->boolean('is_pickable')->default(true);
    $table->boolean('is_receivable')->default(true);
    
    // Coordinates (for robotics/automation)
    $table->decimal('coordinate_x', 10, 2)->nullable();
    $table->decimal('coordinate_y', 10, 2)->nullable();
    $table->decimal('coordinate_z', 10, 2)->nullable();
    
    // Attributes
    $table->string('temperature_zone')->nullable(); // ambient, chilled, frozen
    $table->boolean('is_hazmat_certified')->default(false);
    $table->json('restrictions')->nullable(); // Product type restrictions
});
```

### InventoryLocation Model

```php
class InventoryLocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'type',
        'parent_id',
        'path',
        'depth',
        'address',
        'is_active',
        'priority',
        'max_capacity',
        'capacity_unit',
        'pick_sequence',
        'is_pickable',
        'is_receivable',
        'coordinate_x',
        'coordinate_y',
        'coordinate_z',
        'temperature_zone',
        'is_hazmat_certified',
        'restrictions',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
            'is_pickable' => 'boolean',
            'is_receivable' => 'boolean',
            'is_hazmat_certified' => 'boolean',
            'restrictions' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'parent_id');
    }

    /**
     * @return HasMany<InventoryLocation, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(InventoryLocation::class, 'parent_id');
    }

    /**
     * @return HasManyThrough<InventoryLocation, InventoryLocation, $this>
     */
    public function descendants(): HasManyThrough
    {
        return $this->hasManyThrough(
            InventoryLocation::class,
            InventoryLocation::class,
            'parent_id',
            'parent_id'
        )->where('path', 'like', $this->path . '/%');
    }

    /**
     * Get all ancestor locations
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }
        
        return $ancestors->reverse();
    }

    /**
     * Get warehouse this location belongs to
     */
    public function warehouse(): ?InventoryLocation
    {
        if ($this->type === LocationType::Warehouse) {
            return $this;
        }
        
        return $this->ancestors()->first(fn ($loc) => $loc->type === LocationType::Warehouse);
    }

    /**
     * Calculate current utilization percentage
     */
    public function utilizationPercentage(): float
    {
        if (! $this->max_capacity) {
            return 0;
        }
        
        return round(($this->current_utilization / $this->max_capacity) * 100, 2);
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryLocation $location) {
            if ($location->parent_id) {
                $parent = InventoryLocation::find($location->parent_id);
                $location->path = $parent->path . '/' . $location->code;
                $location->depth = $parent->depth + 1;
            } else {
                $location->path = '/' . $location->code;
                $location->depth = 0;
            }
        });

        static::deleting(function (InventoryLocation $location) {
            $location->children()->delete();
            $location->inventoryLevels()->delete();
        });
    }
}
```

---

## Location Services

### LocationHierarchyService

```php
class LocationHierarchyService
{
    /**
     * Create a bin location with full path
     */
    public function createBin(
        InventoryLocation $warehouse,
        string $zone,
        string $aisle,
        string $rack,
        string $shelf,
        string $bin
    ): InventoryLocation {
        $zoneLocation = $this->findOrCreateChild($warehouse, $zone, LocationType::Zone);
        $aisleLocation = $this->findOrCreateChild($zoneLocation, $aisle, LocationType::Aisle);
        $rackLocation = $this->findOrCreateChild($aisleLocation, $rack, LocationType::Rack);
        $shelfLocation = $this->findOrCreateChild($rackLocation, $shelf, LocationType::Shelf);
        
        return $this->findOrCreateChild($shelfLocation, $bin, LocationType::Bin);
    }

    /**
     * Generate optimal pick path for locations
     */
    public function getPickPath(Collection $locations): Collection
    {
        return $locations->sortBy([
            ['warehouse.priority', 'desc'],
            ['pick_sequence', 'asc'],
            ['coordinate_y', 'asc'], // Aisle
            ['coordinate_x', 'asc'], // Position in aisle
        ]);
    }

    /**
     * Find available bin for putaway
     */
    public function findPutawayLocation(
        InventoryLocation $warehouse,
        InventoryableInterface $item,
        int $quantity
    ): ?InventoryLocation {
        $requiredSpace = $this->calculateSpaceRequired($item, $quantity);
        
        return InventoryLocation::query()
            ->where('path', 'like', $warehouse->path . '/%')
            ->where('type', LocationType::Bin)
            ->where('is_receivable', true)
            ->where('is_active', true)
            ->whereRaw('(max_capacity - current_utilization) >= ?', [$requiredSpace])
            ->when($item->requiresTemperatureControl(), function ($query) use ($item) {
                $query->where('temperature_zone', $item->getTemperatureZone());
            })
            ->when($item->isHazmat(), function ($query) {
                $query->where('is_hazmat_certified', true);
            })
            ->orderBy('pick_sequence')
            ->first();
    }

    /**
     * Get warehouse capacity report
     */
    public function getCapacityReport(InventoryLocation $warehouse): array
    {
        $zones = $warehouse->children()
            ->where('type', LocationType::Zone)
            ->get();
        
        return [
            'warehouse' => [
                'name' => $warehouse->name,
                'total_capacity' => $warehouse->max_capacity,
                'used' => $warehouse->current_utilization,
                'available' => $warehouse->max_capacity - $warehouse->current_utilization,
                'utilization' => $warehouse->utilizationPercentage(),
            ],
            'zones' => $zones->map(fn ($zone) => [
                'name' => $zone->name,
                'total_capacity' => $zone->max_capacity,
                'used' => $zone->current_utilization,
                'utilization' => $zone->utilizationPercentage(),
            ]),
        ];
    }

    private function findOrCreateChild(
        InventoryLocation $parent,
        string $code,
        LocationType $type
    ): InventoryLocation {
        return InventoryLocation::firstOrCreate(
            [
                'parent_id' => $parent->id,
                'code' => $code,
            ],
            [
                'name' => $code,
                'type' => $type,
                'is_active' => true,
            ]
        );
    }
}
```

---

## Barcode System

### Location Barcode Format

```
Barcode: LOC-{WAREHOUSE}-{ZONE}-{AISLE}-{RACK}-{SHELF}-{BIN}
Example: LOC-KLC-A-01-03-2-B

Parsed:
├── LOC      = Location prefix
├── KLC      = Warehouse code
├── A        = Zone
├── 01       = Aisle
├── 03       = Rack
├── 2        = Shelf
└── B        = Bin
```

### LocationBarcodeService

```php
class LocationBarcodeService
{
    public function parse(string $barcode): ?InventoryLocation
    {
        if (! str_starts_with($barcode, 'LOC-')) {
            return null;
        }
        
        $path = str_replace('-', '/', substr($barcode, 4));
        
        return InventoryLocation::where('path', '/' . $path)->first();
    }

    public function generate(InventoryLocation $location): string
    {
        return 'LOC-' . str_replace('/', '-', ltrim($location->path, '/'));
    }
}
```

---

## Temperature Zones

### TemperatureZone Enum

```php
enum TemperatureZone: string
{
    case Ambient = 'ambient';
    case Chilled = 'chilled';      // 2-8°C
    case Frozen = 'frozen';         // -18°C and below
    case DeepFrozen = 'deep_frozen'; // -30°C and below
    case Controlled = 'controlled'; // 15-25°C

    public function label(): string
    {
        return match ($this) {
            self::Ambient => 'Ambient (Room Temp)',
            self::Chilled => 'Chilled (2-8°C)',
            self::Frozen => 'Frozen (-18°C)',
            self::DeepFrozen => 'Deep Frozen (-30°C)',
            self::Controlled => 'Controlled (15-25°C)',
        };
    }

    public function minTemp(): ?int
    {
        return match ($this) {
            self::Ambient => null,
            self::Chilled => 2,
            self::Frozen => -25,
            self::DeepFrozen => -40,
            self::Controlled => 15,
        };
    }

    public function maxTemp(): ?int
    {
        return match ($this) {
            self::Ambient => null,
            self::Chilled => 8,
            self::Frozen => -18,
            self::DeepFrozen => -30,
            self::Controlled => 25,
        };
    }
}
```

---

## Usage Patterns

### Creating Warehouse Structure

```php
$warehouse = InventoryLocation::create([
    'name' => 'KL Central Warehouse',
    'code' => 'KLC',
    'type' => LocationType::Warehouse,
    'address' => 'Shah Alam, Selangor',
    'max_capacity' => 100000,
    'capacity_unit' => 'units',
]);

// Create zones
$picking = InventoryLocation::create([
    'name' => 'Picking Zone',
    'code' => 'PICK',
    'type' => LocationType::Zone,
    'parent_id' => $warehouse->id,
    'is_pickable' => true,
]);

$cold = InventoryLocation::create([
    'name' => 'Cold Storage',
    'code' => 'COLD',
    'type' => LocationType::Zone,
    'parent_id' => $warehouse->id,
    'temperature_zone' => TemperatureZone::Chilled,
]);

// Create bins automatically
$locationService = app(LocationHierarchyService::class);

for ($aisle = 'A'; $aisle <= 'D'; $aisle++) {
    for ($rack = 1; $rack <= 10; $rack++) {
        for ($shelf = 1; $shelf <= 5; $shelf++) {
            for ($bin = 'A'; $bin <= 'C'; $bin++) {
                $locationService->createBin(
                    $warehouse,
                    'PICK',
                    $aisle,
                    str_pad($rack, 2, '0', STR_PAD_LEFT),
                    (string) $shelf,
                    $bin
                );
            }
        }
    }
}
// Creates 600 bins: A-D × 10 racks × 5 shelves × 3 bins
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-stock-level-management.md](03-stock-level-management.md)
