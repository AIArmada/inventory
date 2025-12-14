<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Listeners\CommitInventoryOnPayment;
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
    $this->listener = new CommitInventoryOnPayment($this->allocationService);
});

describe('CommitInventoryOnPayment', function (): void {
    describe('handle', function (): void {
        it('commits allocations when cartId property exists', function (): void {
            // Create allocation first
            $this->allocationService->allocate($this->item, 10, 'commit-cart-123', 30);
            
            expect(InventoryAllocation::where('cart_id', 'commit-cart-123')->count())->toBe(1);

            $event = new class {
                public string $cartId = 'commit-cart-123';
            };

            $this->listener->handle($event);

            // After commit, allocations should be removed (stock is deducted)
            expect(InventoryAllocation::where('cart_id', 'commit-cart-123')->count())->toBe(0);
        });

        it('commits allocations when cart_id property exists', function (): void {
            $this->allocationService->allocate($this->item, 5, 'commit-cart-456', 30);

            $event = new class {
                public string $cart_id = 'commit-cart-456';
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'commit-cart-456')->count())->toBe(0);
        });

        it('extracts cart id from cart object with getId method', function (): void {
            $this->allocationService->allocate($this->item, 5, 'commit-cart-789', 30);

            $cart = new class {
                public function getId(): string
                {
                    return 'commit-cart-789';
                }
            };

            $event = new class($cart) {
                public function __construct(public object $cart) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'commit-cart-789')->count())->toBe(0);
        });

        it('extracts order reference from orderId property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'commit-cart-order', 30);

            $event = new class {
                public string $cartId = 'commit-cart-order';
                public string $orderId = 'order-999';
            };

            // Should not throw and should commit
            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'commit-cart-order')->count())->toBe(0);
        });

        it('handles event with no cart identifier gracefully', function (): void {
            $event = new class {};

            // Should not throw
            $this->listener->handle($event);

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

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'user_default')->count())->toBe(0);
        });

        it('extracts cart id from payment object with cart_id property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'payment-cart-id', 30);

            $payment = new class {
                public string $cart_id = 'payment-cart-id';
            };

            $event = new class($payment) {
                public function __construct(public object $payment) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'payment-cart-id')->count())->toBe(0);
        });

        it('extracts cart id from purchase object with cart_id property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'purchase-cart-id', 30);

            $purchase = new class {
                public string $cart_id = 'purchase-cart-id';
            };

            $event = new class($purchase) {
                public function __construct(public object $purchase) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'purchase-cart-id')->count())->toBe(0);
        });

        it('extracts order reference from payment with order_id property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-with-payment', 30);

            $payment = new class {
                public string $cart_id = 'cart-with-payment';
                public string $order_id = 'order-from-payment';
            };

            $event = new class($payment) {
                public function __construct(public object $payment) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-with-payment')->count())->toBe(0);
        });

        it('extracts order reference from payment with reference property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-ref-test', 30);

            $payment = new class {
                public string $cart_id = 'cart-ref-test';
                public string $reference = 'pay-ref-123';
            };

            $event = new class($payment) {
                public function __construct(public object $payment) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-ref-test')->count())->toBe(0);
        });

        it('extracts order reference from payment with getKey method', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-getkey', 30);

            $payment = new class {
                public string $cart_id = 'cart-getkey';

                public function getKey(): string
                {
                    return 'payment-key-123';
                }
            };

            $event = new class($payment) {
                public function __construct(public object $payment) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-getkey')->count())->toBe(0);
        });

        it('extracts order reference from purchase with getKey method', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-purchase-key', 30);

            $purchase = new class {
                public string $cart_id = 'cart-purchase-key';

                public function getKey(): string
                {
                    return 'purchase-key-456';
                }
            };

            $event = new class($purchase) {
                public function __construct(public object $purchase) {}
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-purchase-key')->count())->toBe(0);
        });

        it('handles cartIdentifier property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-identifier-prop', 30);

            $event = new class {
                public string $cartIdentifier = 'cart-identifier-prop';
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-identifier-prop')->count())->toBe(0);
        });

        it('handles cart_identifier property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart_identifier_prop', 30);

            $event = new class {
                public string $cart_identifier = 'cart_identifier_prop';
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart_identifier_prop')->count())->toBe(0);
        });

        it('handles order_reference property', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-order-ref', 30);

            $event = new class {
                public string $cartId = 'cart-order-ref';
                public string $order_reference = 'order-ref-789';
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-order-ref')->count())->toBe(0);
        });

        it('handles reference property for order', function (): void {
            $this->allocationService->allocate($this->item, 5, 'cart-ref-only', 30);

            $event = new class {
                public string $cartId = 'cart-ref-only';
                public string $reference = 'ref-only-123';
            };

            $this->listener->handle($event);

            expect(InventoryAllocation::where('cart_id', 'cart-ref-only')->count())->toBe(0);
        });
    });
});
