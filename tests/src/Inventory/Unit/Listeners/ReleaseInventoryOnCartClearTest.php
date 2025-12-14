<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Listeners\ReleaseInventoryOnCartClear;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryAllocationService;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->level = InventoryLevel::factory()->create([
        'inventoryable_type' => $this->item->getMorphClass(),
        'inventoryable_id' => $this->item->getKey(),
        'location_id' => $this->location->id,
        'quantity_on_hand' => 100,
        'quantity_reserved' => 0,
    ]);
    $this->allocationService = app(InventoryAllocationService::class);
    $this->listener = new ReleaseInventoryOnCartClear($this->allocationService);
});

describe('ReleaseInventoryOnCartClear', function (): void {
    describe('handleCleared', function (): void {
        it('releases allocations when cartId property exists', function (): void {
            $this->allocationService->allocate($this->item, 10, 'clear-cart-123', 30);

            expect(InventoryAllocation::where('cart_id', 'clear-cart-123')->count())->toBe(1);

            $event = new class {
                public string $cartId = 'clear-cart-123';
            };

            $this->listener->handleCleared($event);

            expect(InventoryAllocation::where('cart_id', 'clear-cart-123')->count())->toBe(0);
        });

        it('releases allocations when cart_id property exists', function (): void {
            $this->allocationService->allocate($this->item, 10, 'clear-cart-456', 30);

            $event = new class {
                public string $cart_id = 'clear-cart-456';
            };

            $this->listener->handleCleared($event);

            expect(InventoryAllocation::where('cart_id', 'clear-cart-456')->count())->toBe(0);
        });

        it('extracts cart id from cart object with getId method', function (): void {
            $this->allocationService->allocate($this->item, 5, 'clear-cart-789', 30);

            $cart = new class {
                public function getId(): string
                {
                    return 'clear-cart-789';
                }
            };

            $event = new class($cart) {
                public function __construct(public object $cart) {}
            };

            $this->listener->handleCleared($event);

            expect(InventoryAllocation::where('cart_id', 'clear-cart-789')->count())->toBe(0);
        });

        it('handles event with no cart identifier gracefully', function (): void {
            $event = new class {};

            // Should not throw
            $this->listener->handleCleared($event);

            expect(true)->toBeTrue();
        });

        it('extracts cart id from cart with getIdentifier and instance methods', function (): void {
            $this->allocationService->allocate($this->item, 5, 'user_default', 30);

            $cart = new class {
                public function getId(): ?string
                {
                    return null;
                }

                public function getIdentifier(): string
                {
                    return 'user';
                }

                public function instance(): string
                {
                    return 'default';
                }
            };

            $event = new class($cart) {
                public function __construct(public object $cart) {}
            };

            $this->listener->handleCleared($event);

            expect(InventoryAllocation::where('cart_id', 'user_default')->count())->toBe(0);
        });
    });

    describe('handleDestroyed', function (): void {
        it('releases allocations when cart is destroyed', function (): void {
            $this->allocationService->allocate($this->item, 10, 'destroy-cart-123', 30);

            $event = new class {
                public string $cartId = 'destroy-cart-123';
            };

            $this->listener->handleDestroyed($event);

            expect(InventoryAllocation::where('cart_id', 'destroy-cart-123')->count())->toBe(0);
        });
    });
});
