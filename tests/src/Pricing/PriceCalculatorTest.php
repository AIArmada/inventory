<?php

declare(strict_types=1);

use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Enums\PromotionType;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Models\Promotion;
use AIArmada\Pricing\Services\PriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

// Test implementation of Priceable interface
class TestPriceableItem implements Priceable
{
    public function __construct(
        private string $id,
        private int $basePrice,
        private ?int $comparePrice = null,
    ) {}

    public function getBuyableIdentifier(): string
    {
        return $this->id;
    }

    public function getBasePrice(): int
    {
        return $this->basePrice;
    }

    public function getComparePrice(): ?int
    {
        return $this->comparePrice;
    }

    public function isOnSale(): bool
    {
        return $this->comparePrice !== null && $this->comparePrice > $this->basePrice;
    }

    public function getDiscountPercentage(): ?float
    {
        if (! $this->isOnSale()) {
            return null;
        }

        return round((($this->comparePrice - $this->basePrice) / $this->comparePrice) * 100, 1);
    }
}

describe('PriceCalculator Service', function (): void {
    beforeEach(function (): void {
        $this->calculator = new PriceCalculator;
    });

    describe('calculate base price', function (): void {
        it('returns base price when no special pricing applies', function (): void {
            $item = new TestPriceableItem('test-item-1', 10000);

            $result = $this->calculator->calculate($item);

            expect($result)->toBeInstanceOf(PriceResultData::class)
                ->and($result->originalPrice)->toBe(10000)
                ->and($result->finalPrice)->toBe(10000)
                ->and($result->discountAmount)->toBe(0)
                ->and($result->discountSource)->toBeNull();
        });

        it('includes base price in breakdown', function (): void {
            $item = new TestPriceableItem('test-item-2', 5000);

            $result = $this->calculator->calculate($item);

            expect($result->breakdown)->not->toBeEmpty()
                ->and($result->breakdown[0]['type'])->toBe('base');
        });

        it('handles zero price', function (): void {
            $item = new TestPriceableItem('test-zero', 0);

            $result = $this->calculator->calculate($item);

            expect($result->originalPrice)->toBe(0)
                ->and($result->finalPrice)->toBe(0)
                ->and($result->discountPercentage)->toBeNull();
        });
    });

    describe('calculate method parameters', function (): void {
        it('accepts quantity parameter', function (): void {
            $item = new TestPriceableItem('test-qty', 1000);

            $result = $this->calculator->calculate($item, 10);

            expect($result)->toBeInstanceOf(PriceResultData::class);
        });

        it('accepts context array parameter', function (): void {
            $item = new TestPriceableItem('test-ctx', 1000);

            $result = $this->calculator->calculate($item, 1, ['customer_id' => 'test']);

            expect($result)->toBeInstanceOf(PriceResultData::class);
        });

        it('accepts segment_ids in context', function (): void {
            $item = new TestPriceableItem('test-seg', 1000);

            $result = $this->calculator->calculate($item, 1, ['segment_ids' => ['vip', 'wholesale']]);

            expect($result)->toBeInstanceOf(PriceResultData::class);
        });

        it('accepts price_list_id in context', function (): void {
            $item = new TestPriceableItem('test-pl', 1000);

            $result = $this->calculator->calculate($item, 1, ['price_list_id' => 'some-id']);

            expect($result)->toBeInstanceOf(PriceResultData::class);
        });
    });

    describe('PriceResult structure', function (): void {
        it('returns all expected fields', function (): void {
            $item = new TestPriceableItem('test-struct', 10000);

            $result = $this->calculator->calculate($item);

            expect($result)->toHaveProperty('originalPrice')
                ->and($result)->toHaveProperty('finalPrice')
                ->and($result)->toHaveProperty('discountAmount')
                ->and($result)->toHaveProperty('discountSource')
                ->and($result)->toHaveProperty('discountPercentage')
                ->and($result)->toHaveProperty('priceListName')
                ->and($result)->toHaveProperty('tierDescription')
                ->and($result)->toHaveProperty('promotionName')
                ->and($result)->toHaveProperty('breakdown');
        });
    });

    describe('customer-specific pricing', function (): void {
        it('returns customer-specific price when available', function (): void {
            $itemId = 'cust-item-' . uniqid();
            $customerId = 'cust-' . uniqid();

            // Create customer-specific price list
            $priceList = PriceList::create([
                'name' => 'Customer Price List',
                'slug' => 'cust-pl-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'customer_id' => $customerId,
            ]);

            // Create price for item in customer price list
            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 8000, // Discounted price
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 1, ['customer_id' => $customerId]);

            expect($result->finalPrice)->toBe(8000)
                ->and($result->discountSource)->toBe('Customer Specific Price')
                ->and($result->breakdown[0]['type'])->toBe('customer_specific');
        });

        it('falls back to base price when customer has no specific pricing', function (): void {
            $itemId = 'no-cust-item-' . uniqid();

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 1, ['customer_id' => 'non-existent-customer']);

            expect($result->finalPrice)->toBe(10000)
                ->and($result->discountSource)->toBeNull();
        });
    });

    describe('segment pricing', function (): void {
        it('returns segment price when available', function (): void {
            $itemId = 'seg-item-' . uniqid();
            $segmentId = 'vip-segment-' . uniqid();

            // Create segment price list
            $priceList = PriceList::create([
                'name' => 'VIP Segment Price List',
                'slug' => 'vip-pl-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'segment_id' => $segmentId,
            ]);

            // Create price for item in segment price list
            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 7500, // VIP price
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 1, ['segment_ids' => [$segmentId]]);

            expect($result->finalPrice)->toBe(7500)
                ->and($result->discountSource)->toBe('Segment Price')
                ->and($result->breakdown[0]['type'])->toBe('segment');
        });

        it('returns best segment price when multiple segments match', function (): void {
            $itemId = 'multi-seg-item-' . uniqid();
            $segment1 = 'segment1-' . uniqid();
            $segment2 = 'segment2-' . uniqid();

            // Create first segment price list
            $priceList1 = PriceList::create([
                'name' => 'Segment 1 List',
                'slug' => 'seg1-pl-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'segment_id' => $segment1,
            ]);

            Price::create([
                'price_list_id' => $priceList1->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 8000,
                'currency' => 'MYR',
            ]);

            // Create second segment price list with better price
            $priceList2 = PriceList::create([
                'name' => 'Segment 2 List',
                'slug' => 'seg2-pl-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'segment_id' => $segment2,
            ]);

            Price::create([
                'price_list_id' => $priceList2->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 7000, // Better price
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 1, ['segment_ids' => [$segment1, $segment2]]);

            // Should return the lowest (best) price
            expect($result->finalPrice)->toBe(7000);
        });
    });

    describe('tier pricing', function (): void {
        it('returns tier price for qualifying quantity', function (): void {
            $itemId = 'tier-item-' . uniqid();

            // Create tier pricing
            PriceTier::create([
                'tierable_type' => TestPriceableItem::class,
                'tierable_id' => $itemId,
                'min_quantity' => 10,
                'max_quantity' => 49,
                'amount' => 900, // Per unit price
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 1000);
            $result = $this->calculator->calculate($item, 25);

            expect($result->finalPrice)->toBe(900)
                ->and($result->discountSource)->toBe('Tier Pricing')
                ->and($result->tierDescription)->toBe('10-49 units')
                ->and($result->breakdown[0]['type'])->toBe('tier');
        });

        it('returns highest matching tier for quantity', function (): void {
            $itemId = 'multi-tier-item-' . uniqid();

            // Create multiple tiers
            PriceTier::create([
                'tierable_type' => TestPriceableItem::class,
                'tierable_id' => $itemId,
                'min_quantity' => 10,
                'max_quantity' => 49,
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            PriceTier::create([
                'tierable_type' => TestPriceableItem::class,
                'tierable_id' => $itemId,
                'min_quantity' => 50,
                'max_quantity' => null,
                'amount' => 800,
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 1000);
            $result = $this->calculator->calculate($item, 100);

            expect($result->finalPrice)->toBe(800)
                ->and($result->tierDescription)->toBe('50+ units');
        });

        it('does not apply tier pricing for quantity of 1', function (): void {
            $itemId = 'single-qty-item-' . uniqid();

            PriceTier::create([
                'tierable_type' => TestPriceableItem::class,
                'tierable_id' => $itemId,
                'min_quantity' => 1,
                'max_quantity' => null,
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 1000);
            $result = $this->calculator->calculate($item, 1);

            // Tier pricing skipped for quantity <= 1
            expect($result->finalPrice)->toBe(1000)
                ->and($result->tierDescription)->toBeNull();
        });
    });

    describe('promotional pricing', function (): void {
        it('applies promotion when item is attached', function (): void {
            $itemId = 'promo-item-' . uniqid();

            $promotion = Promotion::create([
                'name' => 'Summer Sale',
                'type' => PromotionType::Percentage,
                'discount_value' => 20, // 20%
                'is_active' => true,
            ]);

            DB::table(config('pricing.database.tables.promotionables', 'promotionables'))->insert([
                'promotion_id' => $promotion->id,
                'promotionable_type' => TestPriceableItem::class,
                'promotionable_id' => $itemId,
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item);

            expect($result->finalPrice)->toBe(8000)
                ->and($result->discountSource)->toBe('Promotion')
                ->and($result->promotionName)->toBe('Summer Sale')
                ->and($result->breakdown[0]['type'])->toBe('promotion');
        });

        it('skips attached promotion when minimum quantity not met', function (): void {
            $itemId = 'promo-min-qty-item-' . uniqid();

            $promotion = Promotion::create([
                'name' => 'Bulk Only',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'min_quantity' => 3,
                'is_active' => true,
            ]);

            DB::table(config('pricing.database.tables.promotionables', 'promotionables'))->insert([
                'promotion_id' => $promotion->id,
                'promotionable_type' => TestPriceableItem::class,
                'promotionable_id' => $itemId,
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 2);

            expect($result->finalPrice)->toBe(10000)
                ->and($result->promotionName)->toBeNull();
        });

        it('skips attached promotion when minimum purchase not met', function (): void {
            $itemId = 'promo-min-purchase-item-' . uniqid();

            $promotion = Promotion::create([
                'name' => 'Min Spend',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'min_purchase_amount' => 25000, // RM250.00
                'is_active' => true,
            ]);

            DB::table(config('pricing.database.tables.promotionables', 'promotionables'))->insert([
                'promotion_id' => $promotion->id,
                'promotionable_type' => TestPriceableItem::class,
                'promotionable_id' => $itemId,
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 2);

            expect($result->finalPrice)->toBe(10000)
                ->and($result->promotionName)->toBeNull();
        });

        it('does not apply promotion when item is not attached', function (): void {
            $itemId = 'no-promo-item-' . uniqid();

            // Create promotion but don't attach any products
            Promotion::create([
                'name' => 'Summer Sale',
                'type' => PromotionType::Percentage,
                'discount_value' => 20,
                'is_active' => true,
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item);

            // No promotion applied since item isn't attached
            expect($result->finalPrice)->toBe(10000)
                ->and($result->promotionName)->toBeNull();
        });

        it('does not apply inactive promotion', function (): void {
            $itemId = 'inactive-promo-item-' . uniqid();

            $promotion = Promotion::create([
                'name' => 'Inactive Sale',
                'type' => PromotionType::Percentage,
                'discount_value' => 50,
                'is_active' => false,
            ]);

            DB::table(config('pricing.database.tables.promotionables', 'promotionables'))->insert([
                'promotion_id' => $promotion->id,
                'promotionable_type' => TestPriceableItem::class,
                'promotionable_id' => $itemId,
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item);

            expect($result->finalPrice)->toBe(10000)
                ->and($result->promotionName)->toBeNull();
        });

        it('does not apply expired promotion', function (): void {
            $itemId = 'expired-promo-item-' . uniqid();

            $promotion = Promotion::create([
                'name' => 'Expired Sale',
                'type' => PromotionType::Percentage,
                'discount_value' => 30,
                'is_active' => true,
                'ends_at' => now()->subDay(),
            ]);

            DB::table(config('pricing.database.tables.promotionables', 'promotionables'))->insert([
                'promotion_id' => $promotion->id,
                'promotionable_type' => TestPriceableItem::class,
                'promotionable_id' => $itemId,
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item);

            expect($result->finalPrice)->toBe(10000)
                ->and($result->promotionName)->toBeNull();
        });

        it('respects effective_at for promotion scheduling', function (): void {
            $itemId = 'scheduled-promo-item-' . uniqid();

            $startsAt = CarbonImmutable::parse('2025-01-03 10:00:00');
            $beforeStart = $startsAt->subMinute();
            $afterStart = $startsAt->addMinute();

            $promotion = Promotion::create([
                'name' => 'Scheduled Sale',
                'type' => PromotionType::Percentage,
                'discount_value' => 50,
                'is_active' => true,
                'starts_at' => $startsAt,
            ]);

            DB::table(config('pricing.database.tables.promotionables', 'promotionables'))->insert([
                'promotion_id' => $promotion->id,
                'promotionable_type' => TestPriceableItem::class,
                'promotionable_id' => $itemId,
            ]);

            $item = new TestPriceableItem($itemId, 10000);

            $resultBefore = $this->calculator->calculate($item, 1, ['effective_at' => $beforeStart]);
            expect($resultBefore->finalPrice)->toBe(10000)
                ->and($resultBefore->promotionName)->toBeNull();

            $resultAfter = $this->calculator->calculate($item, 1, ['effective_at' => $afterStart]);
            expect($resultAfter->finalPrice)->toBe(5000)
                ->and($resultAfter->promotionName)->toBe('Scheduled Sale');
        });
    });

    describe('price list pricing', function (): void {
        it('returns price from specific price list when provided', function (): void {
            $itemId = 'pl-item-' . uniqid();

            $priceList = PriceList::create([
                'name' => 'Wholesale List',
                'slug' => 'wholesale-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 7000,
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 1, ['price_list_id' => $priceList->id]);

            expect($result->finalPrice)->toBe(7000)
                ->and($result->discountSource)->toBe('Price List')
                ->and($result->priceListName)->toBe('Wholesale List')
                ->and($result->breakdown[0]['type'])->toBe('price_list');
        });

        it('returns price from default price list when no specific list provided', function (): void {
            $itemId = 'default-pl-item-' . uniqid();

            $priceList = PriceList::create([
                'name' => 'Default List',
                'slug' => 'default-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'is_default' => true,
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 9000,
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item);

            expect($result->finalPrice)->toBe(9000)
                ->and($result->priceListName)->toBe('Default List');
        });

        it('respects effective_at for price list scheduling', function (): void {
            $itemId = 'scheduled-pl-item-' . uniqid();

            $startsAt = CarbonImmutable::parse('2025-01-04 10:00:00');
            $beforeStart = $startsAt->subMinute();
            $afterStart = $startsAt->addMinute();

            $priceList = PriceList::create([
                'name' => 'Scheduled Default List',
                'slug' => 'scheduled-default-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'is_default' => true,
                'starts_at' => $startsAt,
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 8000,
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);

            $resultBefore = $this->calculator->calculate($item, 1, ['effective_at' => $beforeStart]);
            expect($resultBefore->finalPrice)->toBe(10000)
                ->and($resultBefore->priceListName)->toBeNull();

            $resultAfter = $this->calculator->calculate($item, 1, ['effective_at' => $afterStart]);
            expect($resultAfter->finalPrice)->toBe(8000)
                ->and($resultAfter->priceListName)->toBe('Scheduled Default List');
        });
    });

    describe('pricing priority', function (): void {
        it('customer price takes precedence over segment price', function (): void {
            $itemId = 'priority-cust-seg-' . uniqid();
            $customerId = 'cust-' . uniqid();
            $segmentId = 'seg-' . uniqid();

            // Customer price list
            $custList = PriceList::create([
                'name' => 'Customer List',
                'slug' => 'cust-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'customer_id' => $customerId,
            ]);

            Price::create([
                'price_list_id' => $custList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 6000, // Customer price
                'currency' => 'MYR',
            ]);

            // Segment price list
            $segList = PriceList::create([
                'name' => 'Segment List',
                'slug' => 'seg-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'segment_id' => $segmentId,
            ]);

            Price::create([
                'price_list_id' => $segList->id,
                'priceable_type' => TestPriceableItem::class,
                'priceable_id' => $itemId,
                'amount' => 7000, // Segment price (worse)
                'currency' => 'MYR',
            ]);

            $item = new TestPriceableItem($itemId, 10000);
            $result = $this->calculator->calculate($item, 1, [
                'customer_id' => $customerId,
                'segment_ids' => [$segmentId],
            ]);

            // Customer price should win
            expect($result->finalPrice)->toBe(6000)
                ->and($result->discountSource)->toBe('Customer Specific Price');
        });
    });
});

describe('TestPriceableItem', function (): void {
    it('implements Priceable interface correctly', function (): void {
        $item = new TestPriceableItem('test-123', 5000, 6000);

        expect($item->getBuyableIdentifier())->toBe('test-123')
            ->and($item->getBasePrice())->toBe(5000)
            ->and($item->getComparePrice())->toBe(6000)
            ->and($item->isOnSale())->toBeTrue()
            ->and($item->getDiscountPercentage())->toBe(16.7);
    });

    it('returns null discount percentage when not on sale', function (): void {
        $item = new TestPriceableItem('test-456', 5000);

        expect($item->isOnSale())->toBeFalse()
            ->and($item->getDiscountPercentage())->toBeNull();
    });

    it('returns null compare price when not set', function (): void {
        $item = new TestPriceableItem('test-789', 5000);

        expect($item->getComparePrice())->toBeNull();
    });

    it('is not on sale when compare price equals base price', function (): void {
        $item = new TestPriceableItem('test-eq', 5000, 5000);

        expect($item->isOnSale())->toBeFalse();
    });

    it('is not on sale when compare price is less than base price', function (): void {
        $item = new TestPriceableItem('test-less', 5000, 4000);

        expect($item->isOnSale())->toBeFalse();
    });
});

describe('Promotion calculateDiscount', function (): void {
    it('calculates percentage discount correctly', function (): void {
        $promotion = new Promotion([
            'type' => PromotionType::Percentage,
            'discount_value' => 20,
        ]);

        expect($promotion->calculateDiscount(10000))->toBe(2000);
        expect($promotion->calculateDiscount(5000))->toBe(1000);
    });

    it('calculates fixed discount correctly', function (): void {
        $promotion = new Promotion([
            'type' => PromotionType::Fixed,
            'discount_value' => 1500,
        ]);

        expect($promotion->calculateDiscount(10000))->toBe(1500);
    });

    it('caps fixed discount at item price', function (): void {
        $promotion = new Promotion([
            'type' => PromotionType::Fixed,
            'discount_value' => 5000,
        ]);

        expect($promotion->calculateDiscount(3000))->toBe(3000);
    });

    it('returns 0 for BuyXGetY type', function (): void {
        $promotion = new Promotion([
            'type' => PromotionType::BuyXGetY,
            'discount_value' => 1,
        ]);

        expect($promotion->calculateDiscount(10000))->toBe(0);
    });
});
