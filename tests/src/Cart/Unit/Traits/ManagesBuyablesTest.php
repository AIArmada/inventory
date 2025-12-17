<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\BuyableInterface;
use AIArmada\Cart\Exceptions\ProductNotPurchasableException;
use AIArmada\Cart\Testing\InMemoryStorage;
use Akaunting\Money\Money;

/**
 * Create a mock buyable product for testing
 */
function createTestBuyable(
    string $id = 'product-1',
    string $name = 'Test Product',
    int $price = 1000,
    ?int $stock = 100,
    int $minQty = 1,
    ?int $maxQty = null,
    int $increment = 1,
    bool $purchasable = true
): BuyableInterface {
    return new class ($id, $name, $price, $stock, $minQty, $maxQty, $increment, $purchasable) implements BuyableInterface {
        public function __construct(
            private string $id,
            private string $name,
            private int $price,
            private ?int $stock,
            private int $minQty,
            private ?int $maxQty,
            private int $increment,
            private bool $purchasable
        ) {
        }

        public function getBuyableIdentifier(): string
        {
            return $this->id;
        }

        public function getBuyableName(): string
        {
            return $this->name;
        }

        public function getBuyableDescription(): ?string
        {
            return 'Test description';
        }

        public function getBuyablePrice(): Money
        {
            return new Money($this->price, new Akaunting\Money\Currency('USD'));
        }

        public function getBuyableStock(): ?int
        {
            return $this->stock;
        }

        public function canBePurchased(?int $quantity = null): bool
        {
            if (!$this->purchasable) {
                return false;
            }
            if ($quantity !== null && $this->stock !== null && $quantity > $this->stock) {
                return false;
            }

            return true;
        }

        public function getBuyableAttributes(): array
        {
            return ['sku' => 'TEST-SKU', 'weight' => 500];
        }

        public function getBuyableSku(): ?string
        {
            return 'TEST-SKU';
        }

        public function getBuyableWeight(): ?int
        {
            return 500;
        }

        public function getBuyableDimensions(): ?array
        {
            return ['length' => 100, 'width' => 100, 'height' => 50];
        }

        public function getMinimumQuantity(): int
        {
            return $this->minQty;
        }

        public function getMaximumQuantity(): ?int
        {
            return $this->maxQty;
        }

        public function getQuantityIncrement(): int
        {
            return $this->increment;
        }

        public function isTaxable(): bool
        {
            return true;
        }

        public function getTaxCategory(): ?string
        {
            return 'standard';
        }
    };
}

describe('ManagesBuyables Trait', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'user-buyable');
    });

    it('adds a buyable product to cart', function (): void {
        $buyable = createTestBuyable();

        $item = $this->cart->addBuyable($buyable);

        expect($item->id)->toBe('product-1')
            ->and($item->name)->toBe('Test Product')
            ->and($item->price)->toBe(1000)
            ->and($item->quantity)->toBe(1);
    });

    it('adds buyable with quantity', function (): void {
        $buyable = createTestBuyable();

        $item = $this->cart->addBuyable($buyable, 3);

        expect($item->quantity)->toBe(3);
    });

    it('adds buyable with extra attributes', function (): void {
        $buyable = createTestBuyable();

        $item = $this->cart->addBuyable($buyable, 1, ['color' => 'red']);

        expect($item->getAttribute('color'))->toBe('red')
            ->and($item->getAttribute('sku'))->toBe('TEST-SKU');
    });

    it('checks if buyable exists in cart', function (): void {
        $buyable = createTestBuyable();

        expect($this->cart->hasBuyable($buyable))->toBeFalse();

        $this->cart->addBuyable($buyable);

        expect($this->cart->hasBuyable($buyable))->toBeTrue();
    });

    it('gets buyable from cart', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable);

        $item = $this->cart->getBuyable($buyable);

        expect($item)->not->toBeNull()
            ->and($item->id)->toBe('product-1');
    });

    it('returns null for non-existent buyable', function (): void {
        $buyable = createTestBuyable();

        expect($this->cart->getBuyable($buyable))->toBeNull();
    });

    it('removes buyable from cart', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable);

        expect($this->cart->hasBuyable($buyable))->toBeTrue();

        $this->cart->removeBuyable($buyable);

        expect($this->cart->hasBuyable($buyable))->toBeFalse();
    });

    it('updates buyable quantity', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable, 2);

        $this->cart->updateBuyable($buyable, 5);

        $item = $this->cart->getBuyable($buyable);
        expect($item->quantity)->toBe(5);
    });

    it('removes buyable when quantity is zero', function (): void {
        $buyable = createTestBuyable();
        $this->cart->addBuyable($buyable);

        $this->cart->updateBuyable($buyable, 0);

        expect($this->cart->hasBuyable($buyable))->toBeFalse();
    });

    it('throws when adding out of stock item', function (): void {
        $buyable = createTestBuyable(stock: 5);

        $this->cart->addBuyable($buyable, 10);
    })->throws(ProductNotPurchasableException::class);

    it('throws when adding inactive product', function (): void {
        $buyable = createTestBuyable(purchasable: false);

        $this->cart->addBuyable($buyable);
    })->throws(ProductNotPurchasableException::class);

    it('throws when below minimum quantity', function (): void {
        $buyable = createTestBuyable(minQty: 5);

        $this->cart->addBuyable($buyable, 2);
    })->throws(ProductNotPurchasableException::class);

    it('throws when above maximum quantity', function (): void {
        $buyable = createTestBuyable(maxQty: 3);

        $this->cart->addBuyable($buyable, 5);
    })->throws(ProductNotPurchasableException::class);

    it('throws when quantity increment is invalid', function (): void {
        $buyable = createTestBuyable(increment: 5);

        $this->cart->addBuyable($buyable, 7); // Not a multiple of 5
    })->throws(ProductNotPurchasableException::class);

    it('calculates total weight', function (): void {
        $buyable = createTestBuyable();

        $this->cart->addBuyable($buyable, 2); // 500g * 2 = 1000g

        expect($this->cart->getTotalWeight())->toBe(1000);
    });

    it('returns zero weight when no items have weight', function (): void {
        $this->cart->add('item-1', 'Product', 100, 1);

        $totalWeight = $this->cart->getTotalWeight();

        expect($totalWeight)->toBe(0);
    });

    it('calculates combined weight for multiple buyables', function (): void {
        $buyable1 = createTestBuyable(id: 'p1');
        $buyable2 = createTestBuyable(id: 'p2');

        $this->cart->addBuyable($buyable1, 2); // 500g * 2
        $this->cart->addBuyable($buyable2, 3); // 500g * 3

        expect($this->cart->getTotalWeight())->toBe(2500);
    });

    it('skips refresh for non-buyable items', function (): void {
        $this->cart->add('item-1', 'Regular Item', 500, 1);

        $changes = $this->cart->refreshBuyablePrices(fn() => createTestBuyable());

        expect($changes)->toBeEmpty();
    });

    it('skips validation for non-buyable items', function (): void {
        $this->cart->add('item-1', 'Regular Item', 500, 1);

        $errors = $this->cart->validateAllBuyables(fn() => createTestBuyable());

        expect($errors)->toBeEmpty();
    });
});

