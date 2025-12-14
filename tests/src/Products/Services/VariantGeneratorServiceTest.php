<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Services\VariantGeneratorService;

describe('VariantGeneratorService', function (): void {
    beforeEach(function (): void {
        $this->service = new VariantGeneratorService();
    });

    describe('generate', function (): void {
        it('returns empty collection when product has no options', function (): void {
            $product = Product::create([
                'name' => 'No Options Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
            ]);

            $variants = $this->service->generate($product);

            expect($variants)->toBeEmpty();
        });

        it('generates variants for a product with one option', function (): void {
            $product = Product::create([
                'name' => 'Single Option Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'SINGLE-OPT',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Red',
                'position' => 0,
            ]);
            OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Blue',
                'position' => 1,
            ]);

            $variants = $this->service->generate($product);

            expect($variants)->toHaveCount(2)
                ->and($variants->first()->is_default)->toBeTrue()
                ->and($variants->last()->is_default)->toBeFalse();
        });

        it('generates variants for a product with multiple options (Cartesian product)', function (): void {
            $product = Product::create([
                'name' => 'Multi Option Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'MULTI-OPT',
            ]);

            $colorOption = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            $sizeOption = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
                'position' => 1,
            ]);

            OptionValue::create(['option_id' => $colorOption->id, 'name' => 'Red', 'position' => 0]);
            OptionValue::create(['option_id' => $colorOption->id, 'name' => 'Blue', 'position' => 1]);

            OptionValue::create(['option_id' => $sizeOption->id, 'name' => 'Small', 'position' => 0]);
            OptionValue::create(['option_id' => $sizeOption->id, 'name' => 'Large', 'position' => 1]);

            $variants = $this->service->generate($product);

            // 2 colors x 2 sizes = 4 variants
            expect($variants)->toHaveCount(4);
        });

        it('deletes existing variants before generating new ones', function (): void {
            $product = Product::create([
                'name' => 'Regenerate Variants Product',
                'price' => 4000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'REGEN-OPT',
            ]);

            // Create an existing variant
            Variant::create([
                'product_id' => $product->id,
                'name' => 'Old Variant',
                'sku' => 'OLD-VARIANT',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Material',
                'position' => 0,
            ]);

            OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Cotton',
                'position' => 0,
            ]);

            $variants = $this->service->generate($product);

            expect($variants)->toHaveCount(1)
                ->and($product->variants()->count())->toBe(1);
        });

        it('throws exception when too many combinations', function (): void {
            config(['products.variants.max_combinations' => 2]);

            $product = Product::create([
                'name' => 'Too Many Options Product',
                'price' => 5000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'TOO-MANY',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            // Create 3 options which exceeds max of 2
            OptionValue::create(['option_id' => $option->id, 'name' => 'Red', 'position' => 0]);
            OptionValue::create(['option_id' => $option->id, 'name' => 'Blue', 'position' => 1]);
            OptionValue::create(['option_id' => $option->id, 'name' => 'Green', 'position' => 2]);

            expect(fn () => $this->service->generate($product))
                ->toThrow(RuntimeException::class, 'Too many variant combinations');
        });

        it('generates proper SKU for variants', function (): void {
            $product = Product::create([
                'name' => 'SKU Test Product',
                'price' => 6000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'SKU-TEST',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
                'position' => 0,
            ]);

            OptionValue::create([
                'option_id' => $option->id,
                'name' => 'XL',
                'position' => 0,
            ]);

            $variants = $this->service->generate($product);

            expect($variants->first()->sku)->toContain('SKU-TEST');
        });
    });

    describe('addVariant', function (): void {
        it('adds a new variant for a specific combination', function (): void {
            $product = Product::create([
                'name' => 'Add Variant Product',
                'price' => 7000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'ADD-VAR',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            $optionValue = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Yellow',
                'position' => 0,
            ]);

            $variant = $this->service->addVariant($product, [$optionValue->id]);

            expect($variant)->toBeInstanceOf(Variant::class)
                ->and($variant->is_default)->toBeTrue()
                ->and($variant->optionValues)->toHaveCount(1);
        });

        it('marks variant as non-default when product already has variants', function (): void {
            $product = Product::create([
                'name' => 'Add Non-Default Variant Product',
                'price' => 8000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'ADD-NON-DEF',
            ]);

            // Create existing variant
            Variant::create([
                'product_id' => $product->id,
                'name' => 'Existing Variant',
                'sku' => 'EXISTING-VAR',
                'is_default' => true,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            $optionValue = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Purple',
                'position' => 0,
            ]);

            $variant = $this->service->addVariant($product, [$optionValue->id]);

            expect($variant->is_default)->toBeFalse();
        });

        it('throws exception when variant with same combination exists', function (): void {
            $product = Product::create([
                'name' => 'Duplicate Variant Product',
                'price' => 9000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'DUP-VAR',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            $optionValue = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Orange',
                'position' => 0,
            ]);

            // Add first variant
            $this->service->addVariant($product, [$optionValue->id]);

            // Try to add duplicate
            expect(fn () => $this->service->addVariant($product, [$optionValue->id]))
                ->toThrow(RuntimeException::class, 'A variant with this combination already exists');
        });
    });

    describe('findVariantByCombination', function (): void {
        it('finds a variant by option value combination', function (): void {
            $product = Product::create([
                'name' => 'Find Variant Product',
                'price' => 10000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'FIND-VAR',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            $optionValue = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Pink',
                'position' => 0,
            ]);

            $createdVariant = $this->service->addVariant($product, [$optionValue->id]);

            $foundVariant = $this->service->findVariantByCombination($product, [$optionValue->id]);

            expect($foundVariant)->not->toBeNull()
                ->and($foundVariant->id)->toBe($createdVariant->id);
        });

        it('returns null when no variant matches combination', function (): void {
            $product = Product::create([
                'name' => 'No Match Product',
                'price' => 11000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
                'sku' => 'NO-MATCH',
            ]);

            $foundVariant = $this->service->findVariantByCombination($product, ['non-existent-id']);

            expect($foundVariant)->toBeNull();
        });
    });
});
