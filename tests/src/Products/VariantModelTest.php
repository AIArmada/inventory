<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

describe('Variant Model', function (): void {
    describe('Variant Creation', function (): void {
        it('can create a variant', function (): void {
            $product = Product::create([
                'name' => 'T-Shirt',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Small Red',
                'sku' => 'TSHIRT-SM-RED-' . uniqid(),
                'price' => 2000,
                'is_enabled' => true,
            ]);

            expect($variant)->toBeInstanceOf(Variant::class)
                ->and($variant->name)->toBe('Small Red');
        });

        it('can have different price than parent product', function (): void {
            $product = Product::create([
                'name' => 'Sneakers',
                'price' => 5000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Limited Edition',
                'sku' => 'SNK-LTD-' . uniqid(),
                'price' => 7500,
                'is_enabled' => true,
            ]);

            expect($variant->price)->toBe(7500)
                ->and($variant->price)->not->toBe($product->price);
        });
    });

    describe('Variant Product Relationship', function (): void {
        it('belongs to a product', function (): void {
            $product = Product::create([
                'name' => 'Jacket',
                'price' => 8000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Large Black',
                'price' => 8000,
                'is_enabled' => true,
            ]);

            expect($variant->product->id)->toBe($product->id);
        });
    });

    describe('Variant Scopes', function (): void {
        it('can filter enabled variants', function (): void {
            $product = Product::create([
                'name' => 'Bag',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]);

            Variant::create(['product_id' => $product->id, 'name' => 'Enabled', 'price' => 3000, 'is_enabled' => true]);
            Variant::create(['product_id' => $product->id, 'name' => 'Disabled', 'price' => 3000, 'is_enabled' => false]);

            expect(Variant::where('product_id', $product->id)->enabled()->count())->toBe(1);
        });

        it('can filter default variant', function (): void {
            $product = Product::create([
                'name' => 'Hat',
                'price' => 1500,
                'status' => ProductStatus::Active,
            ]);

            Variant::create(['product_id' => $product->id, 'name' => 'Default', 'price' => 1500, 'is_default' => true, 'is_enabled' => true]);
            Variant::create(['product_id' => $product->id, 'name' => 'Other', 'price' => 1500, 'is_default' => false, 'is_enabled' => true]);

            expect(Variant::where('product_id', $product->id)->default()->count())->toBe(1);
        });
    });

    describe('Variant Inventory', function (): void {
        it('can track stock quantity', function (): void {
            $product = Product::create([
                'name' => 'Watch',
                'price' => 20000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Gold',
                'price' => 25000,
                'stock_quantity' => 50,
                'is_enabled' => true,
            ]);

            expect($variant->stock_quantity)->toBe(50);
        });
    });
});

describe('Option Model', function (): void {
    it('can create an option', function (): void {
        $product = Product::create([
            'name' => 'Shirt',
            'price' => 2500,
            'status' => ProductStatus::Active,
        ]);

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Size',
        ]);

        expect($option)->toBeInstanceOf(Option::class)
            ->and($option->name)->toBe('Size');
    });

    it('can have multiple values', function (): void {
        $product = Product::create([
            'name' => 'Pants',
            'price' => 4000,
            'status' => ProductStatus::Active,
        ]);

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Color',
        ]);

        OptionValue::create(['option_id' => $option->id, 'name' => 'Red']);
        OptionValue::create(['option_id' => $option->id, 'name' => 'Blue']);
        OptionValue::create(['option_id' => $option->id, 'name' => 'Green']);

        $option->refresh();

        expect($option->values)->toHaveCount(3);
    });
});

describe('OptionValue Model', function (): void {
    it('belongs to an option', function (): void {
        $product = Product::create([
            'name' => 'Dress',
            'price' => 6000,
            'status' => ProductStatus::Active,
        ]);

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Size',
        ]);

        $value = OptionValue::create([
            'option_id' => $option->id,
            'name' => 'Medium',
        ]);

        expect($value->option->id)->toBe($option->id);
    });
});
