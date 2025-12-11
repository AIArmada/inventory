<?php

declare(strict_types=1);

use AIArmada\Pricing\Enums\PromotionType;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Models\Promotion;
use Illuminate\Support\Carbon;

describe('PriceList Model', function (): void {
    describe('PriceList Creation', function (): void {
        it('can create a price list', function (): void {
            $priceList = PriceList::create([
                'name' => 'Retail Prices',
                'slug' => 'retail-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            expect($priceList)->toBeInstanceOf(PriceList::class)
                ->and($priceList->name)->toBe('Retail Prices')
                ->and($priceList->currency)->toBe('MYR');
        });

        it('can create a default price list', function (): void {
            $priceList = PriceList::create([
                'name' => 'Default Prices',
                'slug' => 'default-' . uniqid(),
                'currency' => 'MYR',
                'is_default' => true,
                'is_active' => true,
            ]);

            expect($priceList->is_default)->toBeTrue();
        });
    });

    describe('PriceList Scheduling', function (): void {
        it('is active when within date range', function (): void {
            $priceList = PriceList::create([
                'name' => 'Scheduled Price List',
                'slug' => 'scheduled-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addDay(),
            ]);

            expect($priceList->isActive())->toBeTrue();
        });

        it('is not active when before start date', function (): void {
            $priceList = PriceList::create([
                'name' => 'Future Price List',
                'slug' => 'future-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->addDay(),
                'ends_at' => Carbon::now()->addWeek(),
            ]);

            expect($priceList->isActive())->toBeFalse();
        });

        it('is not active when after end date', function (): void {
            $priceList = PriceList::create([
                'name' => 'Expired Price List',
                'slug' => 'expired-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->subWeek(),
                'ends_at' => Carbon::now()->subDay(),
            ]);

            expect($priceList->isActive())->toBeFalse();
        });

        it('is not active when is_active is false', function (): void {
            $priceList = PriceList::create([
                'name' => 'Disabled Price List',
                'slug' => 'disabled-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => false,
            ]);

            expect($priceList->isActive())->toBeFalse();
        });
    });

    describe('PriceList Priority', function (): void {
        it('can set and use priority for ordering', function (): void {
            $prefix = uniqid();
            PriceList::create(['name' => 'Low', 'slug' => "low-{$prefix}", 'currency' => 'MYR', 'priority' => 1, 'is_active' => true]);
            PriceList::create(['name' => 'High', 'slug' => "high-{$prefix}", 'currency' => 'MYR', 'priority' => 10, 'is_active' => true]);
            PriceList::create(['name' => 'Medium', 'slug' => "medium-{$prefix}", 'currency' => 'MYR', 'priority' => 5, 'is_active' => true]);

            $ordered = PriceList::where('slug', 'like', "%-{$prefix}")->orderBy('priority', 'desc')->get();

            expect($ordered->first()->name)->toBe('High')
                ->and($ordered->last()->name)->toBe('Low');
        });
    });
});

describe('Promotion Model', function (): void {
    describe('Promotion Creation', function (): void {
        it('can create a percentage discount promotion', function (): void {
            $promotion = Promotion::create([
                'name' => '20% Off Sale',
                'code' => 'SALE20-' . uniqid(),
                'type' => PromotionType::Percentage,
                'discount_value' => 2000,
                'is_active' => true,
            ]);

            expect($promotion)->toBeInstanceOf(Promotion::class)
                ->and($promotion->name)->toBe('20% Off Sale')
                ->and($promotion->type)->toBe(PromotionType::Percentage);
        });

        it('can create a fixed amount discount promotion', function (): void {
            $promotion = Promotion::create([
                'name' => 'RM10 Off',
                'code' => 'SAVE10-' . uniqid(),
                'type' => PromotionType::Fixed,
                'discount_value' => 1000,
                'is_active' => true,
            ]);

            expect($promotion->type)->toBe(PromotionType::Fixed)
                ->and($promotion->discount_value)->toBe(1000);
        });
    });

    describe('Promotion Usage Limits', function (): void {
        it('can set usage limit', function (): void {
            $promotion = Promotion::create([
                'name' => 'Limited Promo',
                'code' => 'LIMITED-' . uniqid(),
                'type' => PromotionType::Percentage,
                'discount_value' => 1500,
                'usage_limit' => 100,
                'usage_count' => 0,
                'is_active' => true,
            ]);

            expect($promotion->usage_limit)->toBe(100)
                ->and($promotion->usage_count)->toBe(0);
        });
    });

    describe('Promotion Minimum Requirements', function (): void {
        it('can set minimum purchase amount', function (): void {
            $promotion = Promotion::create([
                'name' => 'Min Purchase',
                'code' => 'MINPURCH-' . uniqid(),
                'type' => PromotionType::Percentage,
                'discount_value' => 1000,
                'min_purchase_amount' => 10000,
                'is_active' => true,
            ]);

            expect($promotion->min_purchase_amount)->toBe(10000);
        });

        it('can set minimum quantity', function (): void {
            $promotion = Promotion::create([
                'name' => 'Min Quantity',
                'code' => 'MINQTY-' . uniqid(),
                'type' => PromotionType::Percentage,
                'discount_value' => 1000,
                'min_quantity' => 3,
                'is_active' => true,
            ]);

            expect($promotion->min_quantity)->toBe(3);
        });
    });

    describe('Promotion Stacking', function (): void {
        it('can mark promotion as stackable', function (): void {
            $stackable = Promotion::create([
                'name' => 'Stackable',
                'code' => 'STACK-' . uniqid(),
                'type' => PromotionType::Percentage,
                'discount_value' => 500,
                'is_stackable' => true,
                'is_active' => true,
            ]);

            $nonStackable = Promotion::create([
                'name' => 'Non-Stackable',
                'code' => 'NOSTACK-' . uniqid(),
                'type' => PromotionType::Percentage,
                'discount_value' => 2000,
                'is_stackable' => false,
                'is_active' => true,
            ]);

            expect($stackable->is_stackable)->toBeTrue()
                ->and($nonStackable->is_stackable)->toBeFalse();
        });
    });
});

describe('Price Model', function (): void {
    it('can create a price for a product', function (): void {
        $priceList = PriceList::create([
            'name' => 'Retail',
            'slug' => 'retail-' . uniqid(),
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        $price = Price::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => 'AIArmada\Products\Models\Product',
            'priceable_id' => 'fake-product-id',
            'amount' => 5000,
            'currency' => 'MYR',
        ]);

        expect($price)->toBeInstanceOf(Price::class)
            ->and($price->amount)->toBe(5000)
            ->and($price->priceList->id)->toBe($priceList->id);
    });

    it('can have compare amount for sale display', function (): void {
        $priceList = PriceList::create([
            'name' => 'Retail',
            'slug' => 'retail-compare-' . uniqid(),
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        $price = Price::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => 'AIArmada\Products\Models\Product',
            'priceable_id' => 'fake-product-id-2',
            'amount' => 4000,
            'compare_amount' => 5000,
            'currency' => 'MYR',
        ]);

        expect($price->amount)->toBe(4000)
            ->and($price->compare_amount)->toBe(5000);
    });
});

describe('PriceTier Model', function (): void {
    it('can create quantity-based price tiers', function (): void {
        $priceList = PriceList::create([
            'name' => 'Wholesale',
            'slug' => 'wholesale-' . uniqid(),
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        $tier1 = PriceTier::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => 'AIArmada\Products\Models\Product',
            'priceable_id' => 'fake-product-id',
            'min_quantity' => 1,
            'max_quantity' => 9,
            'amount' => 1000,
            'currency' => 'MYR',
        ]);

        $tier2 = PriceTier::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => 'AIArmada\Products\Models\Product',
            'priceable_id' => 'fake-product-id',
            'min_quantity' => 10,
            'max_quantity' => 49,
            'amount' => 900,
            'currency' => 'MYR',
        ]);

        $tier3 = PriceTier::create([
            'price_list_id' => $priceList->id,
            'priceable_type' => 'AIArmada\Products\Models\Product',
            'priceable_id' => 'fake-product-id',
            'min_quantity' => 50,
            'max_quantity' => null,
            'amount' => 800,
            'currency' => 'MYR',
        ]);

        expect($tier1->amount)->toBe(1000)
            ->and($tier2->amount)->toBe(900)
            ->and($tier3->amount)->toBe(800)
            ->and($tier3->max_quantity)->toBeNull();
    });
});
