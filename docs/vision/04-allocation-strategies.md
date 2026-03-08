# Allocation Strategies

> **Document:** 04 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Enhance the allocation system with **advanced strategies**, **backorder queuing**, **wave picking support**, and **intelligent split allocation** for optimized order fulfillment.

---

## Strategy Architecture

```
┌────────────────────────────────────────────────────────────┐
│                  ALLOCATION REQUEST                         │
│    Product: SKU-001, Quantity: 100, Cart: cart-123         │
├────────────────────────────────────────────────────────────┤
│                                                             │
│   ┌─────────────────────────────────────────────────────┐  │
│   │            STRATEGY RESOLVER                         │  │
│   │  1. Check product override                           │  │
│   │  2. Check cart/order preferences                     │  │
│   │  3. Use default strategy                             │  │
│   └─────────────────────────────────────────────────────┘  │
│                          │                                  │
│    ┌─────────────────────┼─────────────────────┐           │
│    │                     │                     │           │
│    ▼                     ▼                     ▼           │
│ ┌──────────┐      ┌──────────┐         ┌──────────┐       │
│ │ Priority │      │   FIFO   │         │ Nearest  │       │
│ │ Strategy │      │ Strategy │         │ Strategy │       │
│ └──────────┘      └──────────┘         └──────────┘       │
│                          │                                  │
│                          ▼                                  │
│              ┌────────────────────┐                        │
│              │  ALLOCATION PLAN   │                        │
│              │  Location A: 60    │                        │
│              │  Location B: 40    │                        │
│              └────────────────────┘                        │
│                          │                                  │
│              ┌───────────┴───────────┐                     │
│              ▼                       ▼                     │
│       ┌──────────┐            ┌──────────┐                 │
│       │ ALLOCATE │            │ BACKORDER│                 │
│       │  (100)   │            │   (0)    │                 │
│       └──────────┘            └──────────┘                 │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

---

## Enhanced Strategy Interface

### AllocationStrategy Contract

```php
interface AllocationStrategy
{
    /**
     * Get strategy identifier
     */
    public function getKey(): string;

    /**
     * Get display name
     */
    public function getName(): string;

    /**
     * Generate allocation plan for the given item and quantity
     *
     * @return Collection<int, AllocationPlan>
     */
    public function allocate(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): Collection;

    /**
     * Check if strategy supports split allocation
     */
    public function supportsSplit(): bool;

    /**
     * Get priority order for locations
     */
    public function prioritizeLocations(
        Collection $availableLocations,
        AllocationContext $context
    ): Collection;
}
```

### AllocationContext DTO

```php
class AllocationContext
{
    public function __construct(
        public readonly string $cartId,
        public readonly ?string $orderId = null,
        public readonly ?string $customerId = null,
        public readonly ?Address $shippingAddress = null,
        public readonly ?Carbon $requiredBy = null,
        public readonly bool $allowPartial = true,
        public readonly bool $allowBackorder = false,
        public readonly int $ttlMinutes = 30,
        public readonly array $metadata = [],
    ) {}
}
```

### AllocationPlan DTO

```php
class AllocationPlan
{
    public function __construct(
        public readonly InventoryLocation $location,
        public readonly int $quantity,
        public readonly ?InventoryLevel $level = null,
        public readonly ?string $batchId = null,
        public readonly ?string $serialNumber = null,
    ) {}
}
```

---

## Built-in Strategies

### 1. Priority Strategy (Default)

Allocate from highest-priority location first:

```php
class PriorityStrategy implements AllocationStrategy
{
    public function getKey(): string
    {
        return 'priority';
    }

    public function allocate(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): Collection {
        $remaining = $quantity;
        $plans = collect();
        
        $locations = $this->prioritizeLocations(
            $this->getAvailableLocations($item),
            $context
        );
        
        foreach ($locations as $level) {
            if ($remaining <= 0) {
                break;
            }
            
            $toAllocate = min($remaining, $level->available);
            
            if ($toAllocate > 0) {
                $plans->push(new AllocationPlan(
                    location: $level->location,
                    quantity: $toAllocate,
                    level: $level
                ));
                
                $remaining -= $toAllocate;
            }
        }
        
        return $plans;
    }

    public function prioritizeLocations(Collection $levels, AllocationContext $context): Collection
    {
        return $levels->sortByDesc(fn ($level) => $level->location->priority);
    }

    public function supportsSplit(): bool
    {
        return true;
    }
}
```

### 2. FIFO Strategy

Allocate from locations with oldest stock:

```php
class FifoStrategy implements AllocationStrategy
{
    public function prioritizeLocations(Collection $levels, AllocationContext $context): Collection
    {
        return $levels->sortBy(fn ($level) => $level->last_received_at ?? now());
    }
}
```

### 3. FEFO Strategy (NEW)

First Expired, First Out for perishables:

```php
class FefoStrategy implements AllocationStrategy
{
    public function allocate(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): Collection {
        $remaining = $quantity;
        $plans = collect();
        
        // Get batches ordered by expiry
        $batches = InventoryBatch::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->where('quantity_available', '>', 0)
            ->whereNotNull('expires_at')
            ->orderBy('expires_at')
            ->get();
        
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
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

### 4. Nearest Strategy (NEW)

Allocate from location nearest to shipping address:

```php
class NearestStrategy implements AllocationStrategy
{
    public function __construct(
        private DistanceCalculator $distanceCalculator
    ) {}

    public function prioritizeLocations(Collection $levels, AllocationContext $context): Collection
    {
        if (! $context->shippingAddress) {
            return $levels->sortByDesc(fn ($l) => $l->location->priority);
        }
        
        return $levels->sortBy(function ($level) use ($context) {
            return $this->distanceCalculator->calculate(
                $level->location->coordinates,
                $context->shippingAddress->coordinates
            );
        });
    }
}
```

### 5. Least Stock Strategy

Balance inventory across locations:

```php
class LeastStockStrategy implements AllocationStrategy
{
    public function prioritizeLocations(Collection $levels, AllocationContext $context): Collection
    {
        // Allocate from locations with most stock first
        // This balances inventory across locations
        return $levels->sortByDesc(fn ($level) => $level->quantity_on_hand);
    }
}
```

### 6. Single Location Strategy

Must fulfill entirely from one location:

```php
class SingleLocationStrategy implements AllocationStrategy
{
    public function allocate(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): Collection {
        $level = InventoryLevel::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->where('quantity_on_hand', '>=', $quantity)
            ->whereHas('location', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('location.priority')
            ->first();
        
        if (! $level) {
            return collect(); // Cannot fulfill
        }
        
        return collect([
            new AllocationPlan(
                location: $level->location,
                quantity: $quantity,
                level: $level
            ),
        ]);
    }

    public function supportsSplit(): bool
    {
        return false;
    }
}
```

---

## Strategy Registry

```php
class AllocationStrategyRegistry
{
    /** @var array<string, AllocationStrategy> */
    private array $strategies = [];

    public function register(AllocationStrategy $strategy): self
    {
        $this->strategies[$strategy->getKey()] = $strategy;
        return $this;
    }

    public function get(string $key): AllocationStrategy
    {
        if (! isset($this->strategies[$key])) {
            throw new InvalidAllocationStrategyException("Strategy '{$key}' not found");
        }
        
        return $this->strategies[$key];
    }

    public function resolve(InventoryableInterface $item, AllocationContext $context): AllocationStrategy
    {
        // 1. Check item-level override
        if ($item instanceof HasAllocationStrategy && $item->getAllocationStrategy()) {
            return $this->get($item->getAllocationStrategy());
        }
        
        // 2. Check level override
        $level = InventoryLevel::where('inventoryable_id', $item->getKey())->first();
        if ($level?->allocation_strategy) {
            return $this->get($level->allocation_strategy);
        }
        
        // 3. Use default
        return $this->get(config('inventory.default_strategy', 'priority'));
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->strategies)
            ->mapWithKeys(fn ($s) => [$s->getKey() => $s->getName()])
            ->all();
    }
}
```

---

## Backorder System

### BackorderEntry Model

```php
// Schema
Schema::create('inventory_backorders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->string('cart_id');
    $table->uuid('order_id')->nullable();
    $table->integer('quantity_requested');
    $table->integer('quantity_fulfilled')->default(0);
    $table->string('status')->default('pending'); // pending, partial, fulfilled, cancelled
    $table->timestamp('requested_at');
    $table->timestamp('fulfilled_at')->nullable();
    $table->integer('priority')->default(0); // Higher = more urgent
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
});
```

### BackorderService

```php
class BackorderService
{
    public function createBackorder(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): BackorderEntry {
        return BackorderEntry::create([
            'inventoryable_type' => $item::class,
            'inventoryable_id' => $item->getKey(),
            'cart_id' => $context->cartId,
            'order_id' => $context->orderId,
            'quantity_requested' => $quantity,
            'requested_at' => now(),
            'priority' => $this->calculatePriority($context),
            'metadata' => $context->metadata,
        ]);
    }

    /**
     * Process backorders when inventory is received
     */
    public function processOnReceipt(
        InventoryableInterface $item,
        InventoryLocation $location,
        int $quantityReceived
    ): Collection {
        $fulfilled = collect();
        $remaining = $quantityReceived;
        
        $backorders = BackorderEntry::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->where('status', '!=', 'fulfilled')
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('priority')
            ->orderBy('requested_at')
            ->get();
        
        foreach ($backorders as $backorder) {
            if ($remaining <= 0) {
                break;
            }
            
            $needed = $backorder->quantity_requested - $backorder->quantity_fulfilled;
            $toFulfill = min($remaining, $needed);
            
            // Allocate immediately
            $allocation = app(InventoryAllocationService::class)->allocate(
                $item,
                $toFulfill,
                new AllocationContext(cartId: $backorder->cart_id)
            );
            
            if ($allocation) {
                $backorder->quantity_fulfilled += $toFulfill;
                $backorder->status = $backorder->quantity_fulfilled >= $backorder->quantity_requested
                    ? 'fulfilled'
                    : 'partial';
                $backorder->save();
                
                $remaining -= $toFulfill;
                $fulfilled->push($backorder);
                
                event(new BackorderFulfilled($backorder, $toFulfill));
            }
        }
        
        return $fulfilled;
    }
}
```

---

## Wave Picking Support

### WaveAllocationService

```php
class WaveAllocationService
{
    /**
     * Create wave allocation for multiple orders
     */
    public function createWave(Collection $orders): Wave
    {
        $wave = Wave::create([
            'status' => 'pending',
            'started_at' => now(),
        ]);
        
        // Consolidate items across orders
        $consolidatedItems = $this->consolidateItems($orders);
        
        // Allocate consolidated quantities
        foreach ($consolidatedItems as $item) {
            $allocations = $this->allocateForWave(
                $item['inventoryable'],
                $item['total_quantity'],
                $item['orders']
            );
            
            foreach ($allocations as $allocation) {
                WaveAllocation::create([
                    'wave_id' => $wave->id,
                    'allocation_id' => $allocation->id,
                    'order_id' => $allocation->metadata['order_id'],
                    'pick_sequence' => $allocation->location->pick_sequence,
                ]);
            }
        }
        
        return $wave;
    }

    /**
     * Generate optimized pick list for wave
     */
    public function generatePickList(Wave $wave): Collection
    {
        return WaveAllocation::query()
            ->where('wave_id', $wave->id)
            ->with(['allocation', 'allocation.location'])
            ->get()
            ->sortBy([
                ['allocation.location.zone.name', 'asc'],
                ['allocation.location.pick_sequence', 'asc'],
            ])
            ->map(fn ($wa) => new PickListItem(
                location: $wa->allocation->location,
                product: $wa->allocation->inventoryable,
                quantity: $wa->allocation->quantity,
                orderId: $wa->order_id
            ));
    }
}
```

---

## Enhanced Allocation Service

```php
class EnhancedAllocationService
{
    public function allocate(
        InventoryableInterface $item,
        int $quantity,
        AllocationContext $context
    ): AllocationResult {
        $strategy = $this->strategyRegistry->resolve($item, $context);
        $plans = $strategy->allocate($item, $quantity, $context);
        
        $allocated = 0;
        $allocations = collect();
        
        foreach ($plans as $plan) {
            $allocation = $this->createAllocation($plan, $context);
            $allocations->push($allocation);
            $allocated += $plan->quantity;
        }
        
        $shortfall = $quantity - $allocated;
        
        // Handle backorder if enabled
        if ($shortfall > 0 && $context->allowBackorder) {
            $backorder = $this->backorderService->createBackorder($item, $shortfall, $context);
            
            return new AllocationResult(
                success: $allocated > 0 || $context->allowPartial,
                allocations: $allocations,
                quantityAllocated: $allocated,
                quantityBackordered: $shortfall,
                backorder: $backorder
            );
        }
        
        return new AllocationResult(
            success: $shortfall === 0 || $context->allowPartial,
            allocations: $allocations,
            quantityAllocated: $allocated,
            quantityBackordered: 0
        );
    }
}
```

---

## Configuration

```php
// config/inventory.php
return [
    'allocation' => [
        'default_strategy' => 'priority',
        
        'strategies' => [
            'priority' => PriorityStrategy::class,
            'fifo' => FifoStrategy::class,
            'fefo' => FefoStrategy::class,
            'nearest' => NearestStrategy::class,
            'least_stock' => LeastStockStrategy::class,
            'single_location' => SingleLocationStrategy::class,
        ],
        
        'allow_split' => true,
        'allow_backorder' => false,
        'default_ttl_minutes' => 30,
        
        'wave_picking' => [
            'enabled' => true,
            'min_orders' => 5,
            'max_orders' => 50,
        ],
    ],
];
```

---

## Navigation

**Previous:** [03-stock-level-management.md](03-stock-level-management.md)  
**Next:** [05-batch-lot-tracking.md](05-batch-lot-tracking.md)
