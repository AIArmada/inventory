# Serial Number Management

> **Document:** 06 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Implement **individual unit tracking** with unique serial numbers for high-value items, electronics, and products requiring warranty tracking, theft prevention, or complete unit lifecycle visibility.

---

## Serial Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│                   SERIAL NUMBER LIFECYCLE                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌──────────┐    ┌──────────┐    ┌──────────┐             │
│   │ RECEIVED │───▶│ IN STOCK │───▶│ ALLOCATED│             │
│   └──────────┘    └──────────┘    └──────────┘             │
│        │               │               │                    │
│        │               ▼               ▼                    │
│        │          ┌──────────┐   ┌──────────┐              │
│        │          │QUARANTINE│   │  SHIPPED │              │
│        │          └──────────┘   └──────────┘              │
│        │                              │                     │
│        │                    ┌─────────┴─────────┐          │
│        │                    ▼                   ▼          │
│        │              ┌──────────┐        ┌──────────┐     │
│        │              │ IN USE   │        │ RETURNED │     │
│        │              └──────────┘        └──────────┘     │
│        │                    │                   │          │
│        │                    ▼                   ▼          │
│        │              ┌──────────┐        ┌──────────┐     │
│        │              │ WARRANTY │        │REFURBISH │     │
│        │              └──────────┘        └──────────┘     │
│        │                    │                   │          │
│        │                    ▼                   ▼          │
│        │              ┌──────────┐        ┌──────────┐     │
│        │              │ DISPOSED │◀───────│ RESOLD   │     │
│        │              └──────────┘        └──────────┘     │
│        │                                                    │
│        └──────────────────────────────────────────────────▶│
│                          LOST / STOLEN                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### InventorySerial Migration

```php
Schema::create('inventory_serials', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Polymorphic product link
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    
    // Location tracking
    $table->foreignUuid('location_id')->nullable();
    $table->foreignUuid('level_id')->nullable();
    $table->foreignUuid('batch_id')->nullable(); // Optional batch link
    
    // Serial identification
    $table->string('serial_number')->unique();
    $table->string('manufacturer_serial')->nullable(); // OEM serial
    $table->string('imei')->nullable()->index(); // For mobile devices
    $table->string('mac_address')->nullable(); // For network devices
    
    // Lifecycle status
    $table->string('status')->default('in_stock');
    
    // Ownership
    $table->foreignUuid('customer_id')->nullable();
    $table->foreignUuid('order_id')->nullable();
    $table->timestamp('sold_at')->nullable();
    
    // Warranty
    $table->date('warranty_starts_at')->nullable();
    $table->date('warranty_ends_at')->nullable();
    $table->string('warranty_type')->nullable(); // standard, extended
    $table->json('warranty_claims')->nullable();
    
    // Condition
    $table->string('condition')->default('new'); // new, refurbished, used
    $table->text('condition_notes')->nullable();
    
    // Cost tracking
    $table->integer('unit_cost_minor')->default(0);
    $table->integer('sold_price_minor')->nullable();
    
    // Audit
    $table->timestamp('received_at');
    $table->uuid('received_by')->nullable();
    $table->timestamp('last_scanned_at')->nullable();
    $table->uuid('last_scanned_by')->nullable();
    
    // Metadata
    $table->json('attributes')->nullable(); // Color, size, specs
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    // Indexes
    $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
    $table->index(['customer_id', 'warranty_ends_at']);
});
```

### Serial History Migration

```php
Schema::create('inventory_serial_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('serial_id');
    
    // Status change
    $table->string('from_status')->nullable();
    $table->string('to_status');
    
    // Location change
    $table->foreignUuid('from_location_id')->nullable();
    $table->foreignUuid('to_location_id')->nullable();
    
    // Context
    $table->string('event_type'); // received, allocated, shipped, returned, etc.
    $table->string('reference_type')->nullable();
    $table->uuid('reference_id')->nullable();
    
    // Actor
    $table->foreignUuid('user_id')->nullable();
    $table->text('notes')->nullable();
    
    $table->timestamp('occurred_at');
    $table->timestamps();
    
    $table->index(['serial_id', 'occurred_at']);
});
```

---

## Serial Model

```php
class InventorySerial extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'level_id',
        'batch_id',
        'serial_number',
        'manufacturer_serial',
        'imei',
        'mac_address',
        'status',
        'customer_id',
        'order_id',
        'sold_at',
        'warranty_starts_at',
        'warranty_ends_at',
        'warranty_type',
        'warranty_claims',
        'condition',
        'condition_notes',
        'unit_cost_minor',
        'sold_price_minor',
        'received_at',
        'received_by',
        'attributes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'warranty_starts_at' => 'date',
            'warranty_ends_at' => 'date',
            'received_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'warranty_claims' => 'array',
            'attributes' => 'array',
            'metadata' => 'array',
            'status' => SerialStatus::class,
            'condition' => SerialCondition::class,
        ];
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<InventorySerialHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(InventorySerialHistory::class, 'serial_id');
    }

    /**
     * Check if in stock and available
     */
    public function isAvailable(): bool
    {
        return $this->status === SerialStatus::InStock;
    }

    /**
     * Check if under warranty
     */
    public function isUnderWarranty(): bool
    {
        if (! $this->warranty_ends_at) {
            return false;
        }
        
        return $this->warranty_ends_at->isFuture();
    }

    /**
     * Get warranty days remaining
     */
    public function warrantyDaysRemaining(): ?int
    {
        if (! $this->isUnderWarranty()) {
            return 0;
        }
        
        return now()->diffInDays($this->warranty_ends_at);
    }

    /**
     * Get full serial lifecycle
     */
    public function getLifecycle(): Collection
    {
        return $this->history()
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($h) => [
                'event' => $h->event_type,
                'status' => $h->to_status,
                'location' => $h->toLocation?->name,
                'user' => $h->user?->name,
                'date' => $h->occurred_at,
                'notes' => $h->notes,
            ]);
    }
}
```

---

## Serial Status Enum

```php
enum SerialStatus: string
{
    case Received = 'received';
    case InStock = 'in_stock';
    case Allocated = 'allocated';
    case Shipped = 'shipped';
    case InUse = 'in_use';
    case Returned = 'returned';
    case Refurbishing = 'refurbishing';
    case Quarantine = 'quarantine';
    case Warranty = 'warranty';
    case Lost = 'lost';
    case Stolen = 'stolen';
    case Disposed = 'disposed';

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::Allocated => 'info',
            self::Shipped, self::InUse => 'primary',
            self::Returned, self::Refurbishing => 'warning',
            self::Quarantine, self::Warranty => 'warning',
            self::Lost, self::Stolen, self::Disposed => 'danger',
            default => 'gray',
        };
    }

    public function isAllocatable(): bool
    {
        return $this === self::InStock;
    }

    public function isSellable(): bool
    {
        return in_array($this, [self::InStock, self::Refurbishing]);
    }

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::InStock, self::Quarantine],
            self::InStock => [self::Allocated, self::Quarantine, self::Lost],
            self::Allocated => [self::InStock, self::Shipped],
            self::Shipped => [self::InUse, self::Returned],
            self::InUse => [self::Warranty, self::Returned, self::Lost, self::Stolen],
            self::Returned => [self::InStock, self::Refurbishing, self::Quarantine, self::Disposed],
            self::Refurbishing => [self::InStock, self::Disposed],
            self::Quarantine => [self::InStock, self::Disposed],
            self::Warranty => [self::InUse, self::Returned, self::Disposed],
            default => [],
        };
    }
}
```

---

## Serial Condition Enum

```php
enum SerialCondition: string
{
    case New = 'new';
    case Refurbished = 'refurbished';
    case Used = 'used';
    case Damaged = 'damaged';
    case ForParts = 'for_parts';

    public function priceMultiplier(): float
    {
        return match ($this) {
            self::New => 1.0,
            self::Refurbished => 0.8,
            self::Used => 0.6,
            self::Damaged => 0.3,
            self::ForParts => 0.1,
        };
    }
}
```

---

## Serial Service

```php
class SerialService
{
    /**
     * Receive serialized units
     *
     * @param array<string> $serialNumbers
     * @return Collection<int, InventorySerial>
     */
    public function receiveSerials(
        InventoryableInterface $item,
        InventoryLocation $location,
        array $serialNumbers,
        array $attributes = []
    ): Collection {
        $serials = collect();
        
        foreach ($serialNumbers as $serialNumber) {
            $serial = InventorySerial::create([
                'inventoryable_type' => $item::class,
                'inventoryable_id' => $item->getKey(),
                'location_id' => $location->id,
                'serial_number' => $serialNumber,
                'status' => SerialStatus::InStock,
                'condition' => SerialCondition::New,
                'received_at' => now(),
                'received_by' => auth()->id(),
                ...$attributes,
            ]);
            
            $this->recordHistory($serial, 'received', null, SerialStatus::InStock);
            
            $serials->push($serial);
        }
        
        // Update inventory level
        $level = $this->getOrCreateLevel($item, $location);
        $level->increment('quantity_on_hand', count($serialNumbers));
        
        event(new SerialsReceived($item, $location, $serials));
        
        return $serials;
    }

    /**
     * Allocate specific serial
     */
    public function allocate(InventorySerial $serial, string $cartId): InventorySerial
    {
        if (! $serial->isAvailable()) {
            throw new SerialNotAvailableException($serial);
        }
        
        $serial->update([
            'status' => SerialStatus::Allocated,
            'metadata' => array_merge($serial->metadata ?? [], ['cart_id' => $cartId]),
        ]);
        
        $this->recordHistory($serial, 'allocated', SerialStatus::InStock, SerialStatus::Allocated);
        
        return $serial;
    }

    /**
     * Ship serial to customer
     */
    public function ship(
        InventorySerial $serial,
        string $orderId,
        ?string $customerId = null
    ): void {
        $serial->update([
            'status' => SerialStatus::Shipped,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'sold_at' => now(),
            'warranty_starts_at' => now(),
            'warranty_ends_at' => $this->calculateWarrantyEnd($serial),
        ]);
        
        $this->recordHistory($serial, 'shipped', SerialStatus::Allocated, SerialStatus::Shipped, [
            'order_id' => $orderId,
        ]);
        
        $serial->level->decrement('quantity_on_hand');
    }

    /**
     * Process return
     */
    public function processReturn(
        InventorySerial $serial,
        InventoryLocation $location,
        SerialCondition $condition,
        ?string $notes = null
    ): void {
        $previousLocation = $serial->location;
        
        $serial->update([
            'status' => SerialStatus::Returned,
            'location_id' => $location->id,
            'condition' => $condition,
            'condition_notes' => $notes,
        ]);
        
        $this->recordHistory($serial, 'returned', $serial->status, SerialStatus::Returned, [
            'condition' => $condition->value,
            'notes' => $notes,
        ]);
        
        if ($condition === SerialCondition::New) {
            // Immediately return to stock
            $this->returnToStock($serial);
        } elseif ($condition !== SerialCondition::ForParts) {
            // Send for refurbishment
            $serial->update(['status' => SerialStatus::Refurbishing]);
        }
    }

    /**
     * Return to sellable stock
     */
    public function returnToStock(InventorySerial $serial): void
    {
        $serial->update(['status' => SerialStatus::InStock]);
        $serial->level->increment('quantity_on_hand');
        
        $this->recordHistory($serial, 'restocked', $serial->status, SerialStatus::InStock);
    }

    /**
     * Scan serial for location verification
     */
    public function scan(InventorySerial $serial, InventoryLocation $location): void
    {
        $serial->update([
            'last_scanned_at' => now(),
            'last_scanned_by' => auth()->id(),
        ]);
        
        if ($serial->location_id !== $location->id) {
            // Location mismatch - log discrepancy
            $this->recordHistory($serial, 'location_mismatch', null, null, [
                'expected_location' => $serial->location_id,
                'scanned_location' => $location->id,
            ]);
            
            event(new SerialLocationMismatch($serial, $location));
        }
    }

    private function recordHistory(
        InventorySerial $serial,
        string $eventType,
        ?SerialStatus $fromStatus,
        ?SerialStatus $toStatus,
        array $metadata = []
    ): void {
        InventorySerialHistory::create([
            'serial_id' => $serial->id,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'from_location_id' => $serial->getOriginal('location_id'),
            'to_location_id' => $serial->location_id,
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'occurred_at' => now(),
            'notes' => $metadata['notes'] ?? null,
        ]);
    }

    private function calculateWarrantyEnd(InventorySerial $serial): Carbon
    {
        $duration = $serial->inventoryable->warranty_months ?? 12;
        return now()->addMonths($duration);
    }
}
```

---

## Serial Number Generator

```php
class SerialNumberGenerator
{
    public function generate(InventoryableInterface $item, int $count = 1): array
    {
        $format = config('inventory.serial.format', '{PREFIX}-{YEAR}{MONTH}-{SEQ}');
        $prefix = $this->getPrefix($item);
        $serials = [];
        
        for ($i = 0; $i < $count; $i++) {
            $serials[] = $this->generateOne($format, $prefix);
        }
        
        return $serials;
    }

    private function generateOne(string $format, string $prefix): string
    {
        do {
            $serial = str_replace([
                '{PREFIX}',
                '{YEAR}',
                '{MONTH}',
                '{DAY}',
                '{SEQ}',
                '{RANDOM}',
            ], [
                $prefix,
                now()->format('Y'),
                now()->format('m'),
                now()->format('d'),
                $this->getSequence(),
                strtoupper(Str::random(6)),
            ], $format);
        } while (InventorySerial::where('serial_number', $serial)->exists());
        
        return $serial;
    }

    private function getSequence(): string
    {
        $count = InventorySerial::whereDate('created_at', now())->count() + 1;
        return str_pad($count, 6, '0', STR_PAD_LEFT);
    }
}
```

---

## Warranty Service

```php
class WarrantyService
{
    /**
     * Check warranty status
     */
    public function checkWarranty(string $serialNumber): WarrantyStatus
    {
        $serial = InventorySerial::where('serial_number', $serialNumber)->first();
        
        if (! $serial) {
            return new WarrantyStatus(found: false);
        }
        
        return new WarrantyStatus(
            found: true,
            serial: $serial,
            isUnderWarranty: $serial->isUnderWarranty(),
            warrantyType: $serial->warranty_type,
            expiresAt: $serial->warranty_ends_at,
            daysRemaining: $serial->warrantyDaysRemaining(),
            claimsHistory: $serial->warranty_claims ?? [],
        );
    }

    /**
     * File warranty claim
     */
    public function fileClaim(
        InventorySerial $serial,
        string $reason,
        ?string $description = null
    ): void {
        if (! $serial->isUnderWarranty()) {
            throw new WarrantyExpiredException($serial);
        }
        
        $claims = $serial->warranty_claims ?? [];
        $claims[] = [
            'id' => Str::uuid()->toString(),
            'reason' => $reason,
            'description' => $description,
            'filed_at' => now()->toIso8601String(),
            'filed_by' => auth()->id(),
            'status' => 'pending',
        ];
        
        $serial->update([
            'warranty_claims' => $claims,
            'status' => SerialStatus::Warranty,
        ]);
        
        event(new WarrantyClaimFiled($serial, end($claims)));
    }
}
```

---

## Usage Examples

### Receiving Serialized Products

```php
$serialService = app(SerialService::class);

// Receive with known serials
$serials = $serialService->receiveSerials(
    item: $laptop,
    location: $warehouse,
    serialNumbers: ['SN001', 'SN002', 'SN003'],
    attributes: [
        'manufacturer_serial' => 'MFG-12345',
        'unit_cost_minor' => 350000, // RM3,500
    ]
);

// Generate and receive
$generator = app(SerialNumberGenerator::class);
$newSerials = $generator->generate($laptop, 10);
$received = $serialService->receiveSerials($laptop, $warehouse, $newSerials);
```

### Allocating and Shipping

```php
// Customer selects specific serial (or auto-select)
$serial = InventorySerial::where('serial_number', 'SN001')
    ->where('status', SerialStatus::InStock)
    ->first();

$serialService->allocate($serial, 'cart-123');

// On order completion
$serialService->ship($serial, 'ORD-456', 'customer-789');
```

### Warranty Check

```php
$warrantyService = app(WarrantyService::class);

$status = $warrantyService->checkWarranty('SN001');

if ($status->isUnderWarranty) {
    echo "Warranty valid until: {$status->expiresAt->format('Y-m-d')}";
    echo "Days remaining: {$status->daysRemaining}";
}
```

---

## Navigation

**Previous:** [05-batch-lot-tracking.md](05-batch-lot-tracking.md)  
**Next:** [07-cost-valuation.md](07-cost-valuation.md)
