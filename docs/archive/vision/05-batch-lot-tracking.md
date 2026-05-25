# Batch & Lot Tracking

> **Document:** 05 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Introduce **batch and lot tracking** for inventory with expiry dates, production dates, recall capabilities, and FEFO (First Expired, First Out) allocation for perishables and regulated products.

---

## Batch Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│                    BATCH LIFECYCLE                           │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐ │
│   │ CREATED │───▶│ ACTIVE  │───▶│ EXPIRED │───▶│ DISPOSED│ │
│   └─────────┘    └─────────┘    └─────────┘    └─────────┘ │
│        │              │              │                      │
│        │              │              │                      │
│        │         ┌────┴────┐        │                      │
│        │         │         │        │                      │
│        │    ┌────┴───┐ ┌───┴────┐   │                      │
│        │    │ALLOCATED│ │ SOLD  │   │                      │
│        │    └────────┘ └────────┘   │                      │
│        │                            │                      │
│        └────────────────────────────┘                      │
│                    RECALLED                                 │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### InventoryBatch Migration

```php
Schema::create('inventory_batches', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Polymorphic product link
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    
    // Location
    $table->foreignUuid('location_id');
    $table->foreignUuid('level_id');
    
    // Batch identification
    $table->string('batch_number')->index();
    $table->string('lot_number')->nullable()->index();
    $table->string('supplier_batch')->nullable(); // Supplier's batch reference
    
    // Dates
    $table->date('manufactured_at')->nullable();
    $table->date('received_at');
    $table->date('expires_at')->nullable()->index();
    $table->date('best_before_at')->nullable();
    
    // Quantities
    $table->integer('quantity_received');
    $table->integer('quantity_on_hand')->default(0);
    $table->integer('quantity_reserved')->default(0);
    $table->integer('quantity_shipped')->default(0);
    $table->integer('quantity_adjusted')->default(0);
    $table->integer('quantity_disposed')->default(0);
    
    // Computed
    $table->integer('quantity_available')->storedAs('quantity_on_hand - quantity_reserved');
    
    // Cost
    $table->integer('unit_cost_minor')->default(0);
    
    // Status
    $table->string('status')->default('active'); // active, quarantine, expired, recalled, disposed
    
    // Quality
    $table->string('quality_status')->default('passed'); // pending, passed, failed
    $table->text('quality_notes')->nullable();
    $table->uuid('inspected_by')->nullable();
    $table->timestamp('inspected_at')->nullable();
    
    // Recall tracking
    $table->string('recall_reason')->nullable();
    $table->timestamp('recalled_at')->nullable();
    $table->uuid('recalled_by')->nullable();
    
    // Metadata
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    // Indexes
    $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
    $table->index(['location_id', 'expires_at']);
    $table->unique(['inventoryable_type', 'inventoryable_id', 'location_id', 'batch_number']);
});
```

---

## Batch Model

```php
class InventoryBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'level_id',
        'batch_number',
        'lot_number',
        'supplier_batch',
        'manufactured_at',
        'received_at',
        'expires_at',
        'best_before_at',
        'quantity_received',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_shipped',
        'quantity_adjusted',
        'quantity_disposed',
        'unit_cost_minor',
        'status',
        'quality_status',
        'quality_notes',
        'inspected_by',
        'inspected_at',
        'recall_reason',
        'recalled_at',
        'recalled_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'received_at' => 'date',
            'expires_at' => 'date',
            'best_before_at' => 'date',
            'inspected_at' => 'datetime',
            'recalled_at' => 'datetime',
            'status' => BatchStatus::class,
            'quality_status' => QualityStatus::class,
            'metadata' => 'array',
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
     * @return BelongsTo<InventoryLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(InventoryLevel::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if batch is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if batch is expiring soon
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at 
            && $this->expires_at->isFuture()
            && $this->expires_at->diffInDays(now()) <= $days;
    }

    /**
     * Get days until expiry
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }
        
        return max(0, $this->expires_at->diffInDays(now(), false));
    }

    /**
     * Check if allocatable
     */
    public function isAllocatable(): bool
    {
        return $this->status === BatchStatus::Active
            && $this->quality_status === QualityStatus::Passed
            && $this->quantity_available > 0
            && ! $this->isExpired();
    }

    /**
     * Get shelf life percentage remaining
     */
    public function shelfLifeRemaining(): ?float
    {
        if (! $this->manufactured_at || ! $this->expires_at) {
            return null;
        }
        
        $totalLife = $this->manufactured_at->diffInDays($this->expires_at);
        $remaining = max(0, now()->diffInDays($this->expires_at, false));
        
        return round(($remaining / $totalLife) * 100, 1);
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryBatch $batch) {
            if (! $batch->batch_number) {
                $batch->batch_number = app(BatchNumberGenerator::class)->generate($batch);
            }
        });

        static::updated(function (InventoryBatch $batch) {
            // Sync level quantities
            $batch->level->quantity_on_hand = InventoryBatch::query()
                ->where('level_id', $batch->level_id)
                ->where('status', BatchStatus::Active)
                ->sum('quantity_on_hand');
            $batch->level->save();
        });
    }
}
```

---

## Batch Status Enum

```php
enum BatchStatus: string
{
    case Active = 'active';
    case Quarantine = 'quarantine';
    case Expired = 'expired';
    case Recalled = 'recalled';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Quarantine => 'In Quarantine',
            self::Expired => 'Expired',
            self::Recalled => 'Recalled',
            self::Disposed => 'Disposed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Quarantine => 'warning',
            self::Expired => 'danger',
            self::Recalled => 'danger',
            self::Disposed => 'gray',
        };
    }

    public function isAllocatable(): bool
    {
        return $this === self::Active;
    }
}
```

---

## Batch Number Generator

```php
class BatchNumberGenerator
{
    public function generate(InventoryBatch $batch): string
    {
        $format = config('inventory.batch.number_format', '{PREFIX}-{DATE}-{SEQ}');
        
        $tokens = [
            '{PREFIX}' => $this->getPrefix($batch),
            '{DATE}' => now()->format('Ymd'),
            '{YEAR}' => now()->format('Y'),
            '{MONTH}' => now()->format('m'),
            '{DAY}' => now()->format('d'),
            '{SEQ}' => $this->getSequence($batch),
            '{LOCATION}' => $batch->location?->code ?? 'XX',
            '{RANDOM}' => strtoupper(Str::random(4)),
        ];
        
        return str_replace(array_keys($tokens), array_values($tokens), $format);
    }

    private function getPrefix(InventoryBatch $batch): string
    {
        // Product-specific prefix or default
        if (method_exists($batch->inventoryable, 'getBatchPrefix')) {
            return $batch->inventoryable->getBatchPrefix();
        }
        
        return config('inventory.batch.default_prefix', 'BTH');
    }

    private function getSequence(InventoryBatch $batch): string
    {
        $today = now()->format('Ymd');
        
        $count = InventoryBatch::query()
            ->whereDate('created_at', now())
            ->count() + 1;
        
        return str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
```

---

## Batch Service

```php
class BatchService
{
    /**
     * Receive inventory with batch tracking
     */
    public function receive(
        InventoryableInterface $item,
        InventoryLocation $location,
        int $quantity,
        array $batchData
    ): InventoryBatch {
        $level = $this->getOrCreateLevel($item, $location);
        
        $batch = InventoryBatch::create([
            'inventoryable_type' => $item::class,
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'level_id' => $level->id,
            'quantity_received' => $quantity,
            'quantity_on_hand' => $quantity,
            'received_at' => now(),
            'status' => BatchStatus::Active,
            ...$batchData,
        ]);
        
        // Update level totals
        $level->increment('quantity_on_hand', $quantity);
        
        event(new BatchReceived($batch));
        
        return $batch;
    }

    /**
     * Ship from specific batch
     */
    public function ship(InventoryBatch $batch, int $quantity): void
    {
        if ($quantity > $batch->quantity_available) {
            throw new InsufficientBatchQuantityException($batch, $quantity);
        }
        
        $batch->decrement('quantity_on_hand', $quantity);
        $batch->increment('quantity_shipped', $quantity);
        
        $batch->level->decrement('quantity_on_hand', $quantity);
        
        event(new BatchShipped($batch, $quantity));
    }

    /**
     * Transfer batch to another location
     */
    public function transfer(
        InventoryBatch $batch,
        InventoryLocation $toLocation,
        int $quantity
    ): InventoryBatch {
        if ($quantity > $batch->quantity_available) {
            throw new InsufficientBatchQuantityException($batch, $quantity);
        }
        
        // Decrease from source
        $batch->decrement('quantity_on_hand', $quantity);
        $batch->level->decrement('quantity_on_hand', $quantity);
        
        // Create or update destination batch
        $toLevel = $this->getOrCreateLevel($batch->inventoryable, $toLocation);
        
        $toBatch = InventoryBatch::firstOrNew([
            'inventoryable_type' => $batch->inventoryable_type,
            'inventoryable_id' => $batch->inventoryable_id,
            'location_id' => $toLocation->id,
            'batch_number' => $batch->batch_number,
        ], [
            'level_id' => $toLevel->id,
            'lot_number' => $batch->lot_number,
            'manufactured_at' => $batch->manufactured_at,
            'expires_at' => $batch->expires_at,
            'received_at' => now(),
            'unit_cost_minor' => $batch->unit_cost_minor,
            'status' => $batch->status,
        ]);
        
        $toBatch->quantity_on_hand += $quantity;
        $toBatch->quantity_received += $quantity;
        $toBatch->save();
        
        $toLevel->increment('quantity_on_hand', $quantity);
        
        event(new BatchTransferred($batch, $toBatch, $quantity));
        
        return $toBatch;
    }

    /**
     * Dispose expired or recalled batch
     */
    public function dispose(InventoryBatch $batch, string $reason): void
    {
        $quantityDisposed = $batch->quantity_on_hand;
        
        $batch->update([
            'quantity_disposed' => $batch->quantity_disposed + $quantityDisposed,
            'quantity_on_hand' => 0,
            'status' => BatchStatus::Disposed,
        ]);
        
        $batch->level->decrement('quantity_on_hand', $quantityDisposed);
        
        event(new BatchDisposed($batch, $quantityDisposed, $reason));
    }
}
```

---

## Recall Management

```php
class RecallService
{
    /**
     * Initiate a batch recall
     */
    public function initiateRecall(
        string $batchNumber,
        string $reason,
        User $initiatedBy
    ): RecallReport {
        $batches = InventoryBatch::where('batch_number', $batchNumber)->get();
        
        if ($batches->isEmpty()) {
            throw new BatchNotFoundException($batchNumber);
        }
        
        $report = new RecallReport($batchNumber, $reason);
        
        foreach ($batches as $batch) {
            // Update batch status
            $batch->update([
                'status' => BatchStatus::Recalled,
                'recall_reason' => $reason,
                'recalled_at' => now(),
                'recalled_by' => $initiatedBy->id,
            ]);
            
            // Release any allocations
            $this->releaseAllocations($batch);
            
            // Track affected quantities
            $report->addAffectedBatch($batch);
        }
        
        // Find shipped orders with this batch
        $affectedOrders = $this->findAffectedOrders($batchNumber);
        $report->setAffectedOrders($affectedOrders);
        
        event(new BatchRecalled($batchNumber, $reason, $batches, $affectedOrders));
        
        return $report;
    }

    private function findAffectedOrders(string $batchNumber): Collection
    {
        return InventoryMovement::query()
            ->where('type', MovementType::Shipment)
            ->whereJsonContains('metadata->batch_number', $batchNumber)
            ->with('order')
            ->get()
            ->pluck('order')
            ->unique();
    }
}
```

---

## FEFO Allocation

```php
class FefoAllocationStrategy implements AllocationStrategy
{
    public function allocate(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): Collection {
        $remaining = $quantity;
        $plans = collect();
        
        $batches = InventoryBatch::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->where('status', BatchStatus::Active)
            ->where('quality_status', QualityStatus::Passed)
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'asc') // First expired first
            ->orderBy('manufactured_at', 'asc')
            ->get();
        
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            
            // Check minimum shelf life requirement
            if ($context->metadata['min_shelf_life_days'] ?? 0) {
                if ($batch->daysUntilExpiry() < $context->metadata['min_shelf_life_days']) {
                    continue;
                }
            }
            
            $toAllocate = min($remaining, $batch->quantity_available);
            
            $plans->push(new AllocationPlan(
                location: $batch->location,
                quantity: $toAllocate,
                level: $batch->level,
                batchId: $batch->id
            ));
            
            $remaining -= $toAllocate;
        }
        
        return $plans;
    }
}
```

---

## Expiry Monitoring

```php
class ExpiryMonitoringService
{
    /**
     * Get batches expiring within period
     */
    public function getExpiringBatches(int $days = 30): Collection
    {
        return InventoryBatch::query()
            ->where('status', BatchStatus::Active)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Get expired batches requiring action
     */
    public function getExpiredBatches(): Collection
    {
        return InventoryBatch::query()
            ->where('status', BatchStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('quantity_on_hand', '>', 0)
            ->get();
    }

    /**
     * Auto-expire and notify
     */
    public function processExpirations(): void
    {
        $expired = $this->getExpiredBatches();
        
        foreach ($expired as $batch) {
            $batch->update(['status' => BatchStatus::Expired]);
            
            event(new BatchExpired($batch));
        }
        
        // Notify about expiring soon
        $expiringSoon = $this->getExpiringBatches(7);
        
        if ($expiringSoon->isNotEmpty()) {
            event(new BatchesExpiringSoon($expiringSoon));
        }
    }
}
```

---

## Usage Examples

### Receiving with Batch

```php
$batch = $batchService->receive(
    item: $product,
    location: $warehouse,
    quantity: 500,
    batchData: [
        'lot_number' => 'LOT-2024-001',
        'manufactured_at' => Carbon::parse('2024-01-15'),
        'expires_at' => Carbon::parse('2025-01-15'),
        'unit_cost_minor' => 1500, // RM15.00
    ]
);

echo $batch->batch_number; // BTH-20240520-0001
echo $batch->daysUntilExpiry(); // 240
echo $batch->shelfLifeRemaining(); // 65.8%
```

### FEFO Allocation

```php
$allocation = $allocationService->allocate(
    item: $product,
    quantity: 100,
    context: new AllocationContext(
        cartId: 'cart-123',
        metadata: [
            'min_shelf_life_days' => 60, // Require 60 days minimum
        ]
    )
);

// Allocates from batches expiring soonest
foreach ($allocation->plans as $plan) {
    echo "Batch: {$plan->batchId}, Qty: {$plan->quantity}";
}
```

---

## Navigation

**Previous:** [04-allocation-strategies.md](04-allocation-strategies.md)  
**Next:** [06-serial-numbers.md](06-serial-numbers.md)
