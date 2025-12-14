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

        it('has correct default attributes', function (): void {
            $product = Product::create([
                'name' => 'Default Test Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Default Variant',
                'sku' => 'DEF-TEST-' . uniqid(),
            ]);

            expect($variant->is_default)->toBeFalse()
                ->and($variant->is_enabled)->toBeTrue();
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

    describe('Price Helpers', function (): void {
        it('gets effective price from variant when set', function (): void {
            $product = Product::create([
                'name' => 'Price Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Price Variant',
                'sku' => 'PRICE-VAR-' . uniqid(),
                'price' => 1500,
            ]);

            expect($variant->getEffectivePrice())->toBe(1500);
        });

        it('falls back to parent product price when variant price is null', function (): void {
            $product = Product::create([
                'name' => 'Fallback Price Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Fallback Variant',
                'sku' => 'FALLBACK-VAR-' . uniqid(),
                'price' => null,
            ]);

            expect($variant->getEffectivePrice())->toBe(2000);
        });

        it('can format price', function (): void {
            $product = Product::create([
                'name' => 'Format Price Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Format Variant',
                'sku' => 'FORMAT-VAR-' . uniqid(),
                'price' => 3500,
            ]);

            expect($variant->getFormattedPrice())->toContain('3,500');
        });

        it('gets effective compare price from variant when set', function (): void {
            $product = Product::create([
                'name' => 'Compare Price Product',
                'price' => 4000,
                'compare_price' => 5000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Compare Variant',
                'sku' => 'COMPARE-VAR-' . uniqid(),
                'compare_price' => 6000,
            ]);

            expect($variant->getEffectiveComparePrice())->toBe(6000);
        });

        it('falls back to parent compare price when variant compare price is null', function (): void {
            $product = Product::create([
                'name' => 'Fallback Compare Product',
                'price' => 4000,
                'compare_price' => 5500,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Fallback Compare Variant',
                'sku' => 'FB-COMPARE-VAR-' . uniqid(),
                'compare_price' => null,
            ]);

            expect($variant->getEffectiveComparePrice())->toBe(5500);
        });

        it('can format compare price', function (): void {
            $product = Product::create([
                'name' => 'Format Compare Product',
                'price' => 4000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Format Compare Variant',
                'sku' => 'FMT-COMPARE-VAR-' . uniqid(),
                'compare_price' => 5000,
            ]);

            expect($variant->getFormattedComparePrice())->toContain('5,000');
        });

        it('returns null for formatted compare price when no compare price', function (): void {
            $product = Product::create([
                'name' => 'No Compare Product',
                'price' => 4000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'No Compare Variant',
                'sku' => 'NO-COMPARE-VAR-' . uniqid(),
                'compare_price' => null,
            ]);
            $variant->product->compare_price = null;

            expect($variant->getFormattedComparePrice())->toBeNull();
        });
    });

    describe('Option Helpers', function (): void {
        it('gets option summary as string', function (): void {
            $product = Product::create([
                'name' => 'Option Summary Product',
                'price' => 5000,
                'status' => ProductStatus::Active,
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

            $red = OptionValue::create(['option_id' => $colorOption->id, 'name' => 'Red', 'position' => 0]);
            $large = OptionValue::create(['option_id' => $sizeOption->id, 'name' => 'Large', 'position' => 0]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Option Summary Variant',
                'sku' => 'OPT-SUM-' . uniqid(),
            ]);

            $variant->optionValues()->attach([$red->id, $large->id]);

            expect($variant->getOptionSummary())->toBe('Red / Large');
        });

        it('gets full name including product name', function (): void {
            $product = Product::create([
                'name' => 'Full Name Product',
                'price' => 6000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
                'position' => 0,
            ]);

            $medium = OptionValue::create(['option_id' => $option->id, 'name' => 'Medium', 'position' => 0]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Full Name Variant',
                'sku' => 'FULL-NAME-' . uniqid(),
            ]);

            $variant->optionValues()->attach($medium->id);

            expect($variant->getFullName())->toBe('Full Name Product - Medium');
        });

        it('returns product name only when no options', function (): void {
            $product = Product::create([
                'name' => 'No Options Product',
                'price' => 7000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'No Options Variant',
                'sku' => 'NO-OPT-' . uniqid(),
            ]);

            expect($variant->getFullName())->toBe('No Options Product');
        });
    });

    describe('Status Helpers', function (): void {
        it('checks if variant is enabled', function (): void {
            $product = Product::create([
                'name' => 'Enabled Check Product',
                'price' => 8000,
                'status' => ProductStatus::Active,
            ]);

            $enabledVariant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Enabled Variant',
                'sku' => 'ENABLED-' . uniqid(),
                'is_enabled' => true,
            ]);

            $disabledVariant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Disabled Variant',
                'sku' => 'DISABLED-' . uniqid(),
                'is_enabled' => false,
            ]);

            expect($enabledVariant->isEnabled())->toBeTrue()
                ->and($disabledVariant->isEnabled())->toBeFalse();
        });

        it('checks if variant is purchasable', function (): void {
            $activeProduct = Product::create([
                'name' => 'Active Product',
                'price' => 9000,
                'status' => ProductStatus::Active,
            ]);

            $draftProduct = Product::create([
                'name' => 'Draft Product',
                'price' => 9000,
                'status' => ProductStatus::Draft,
            ]);

            $purchasableVariant = Variant::create([
                'product_id' => $activeProduct->id,
                'name' => 'Purchasable Variant',
                'sku' => 'PURCHASABLE-' . uniqid(),
                'is_enabled' => true,
            ]);

            $notPurchasableVariant = Variant::create([
                'product_id' => $draftProduct->id,
                'name' => 'Not Purchasable Variant',
                'sku' => 'NOT-PURCHASABLE-' . uniqid(),
                'is_enabled' => true,
            ]);

            $disabledVariant = Variant::create([
                'product_id' => $activeProduct->id,
                'name' => 'Disabled Not Purchasable Variant',
                'sku' => 'DISABLED-NOT-PURCH-' . uniqid(),
                'is_enabled' => false,
            ]);

            expect($purchasableVariant->isPurchasable())->toBeTrue()
                ->and($notPurchasableVariant->isPurchasable())->toBeFalse()
                ->and($disabledVariant->isPurchasable())->toBeFalse();
        });
    });

    describe('SKU Generation', function (): void {
        it('can generate SKU based on option values', function (): void {
            $product = Product::create([
                'name' => 'SKU Gen Product',
                'price' => 10000,
                'status' => ProductStatus::Active,
                'sku' => 'SKU-GEN',
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
                'position' => 0,
            ]);

            $blue = OptionValue::create(['option_id' => $option->id, 'name' => 'Blue', 'position' => 0]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Placeholder Variant',
                'sku' => 'PLACEHOLDER-' . uniqid(),
            ]);

            $variant->optionValues()->attach($blue->id);

            $generatedSku = $variant->generateSku();

            expect($generatedSku)->toContain('SKU-GEN')
                ->and($generatedSku)->toContain('BL');
        });
    });

    describe('Media Collections', function (): void {
        it('registers variant_images media collection', function (): void {
            $product = Product::create([
                'name' => 'Media Collection Product',
                'price' => 11000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Media Variant',
                'sku' => 'MEDIA-' . uniqid(),
            ]);

            $mediaCollections = collect($variant->getRegisteredMediaCollections());

            expect($mediaCollections->pluck('name'))->toContain('variant_images');
        });
    });

    describe('Deletion', function (): void {
        it('detaches option values when variant is deleted', function (): void {
            $product = Product::create([
                'name' => 'Delete Variant Product',
                'price' => 12000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
                'position' => 0,
            ]);

            $small = OptionValue::create(['option_id' => $option->id, 'name' => 'Small', 'position' => 0]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Delete Variant',
                'sku' => 'DELETE-VAR-' . uniqid(),
            ]);

            $variant->optionValues()->attach($small->id);
            $variantId = $variant->id;

            $variant->delete();

            expect(\Illuminate\Support\Facades\DB::table(config('products.tables.variant_options', 'product_variant_options'))
                ->where('variant_id', $variantId)->count())->toBe(0);
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

    it('has correct default attributes', function (): void {
        $product = Product::create([
            'name' => 'Default Option Product',
            'price' => 5000,
            'status' => ProductStatus::Active,
        ]);

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Material',
        ]);

        expect($option->position)->toBe(0)
            ->and($option->is_visible)->toBeTrue();
    });

    it('belongs to a product', function (): void {
        $product = Product::create([
            'name' => 'Option Product Relationship',
            'price' => 6000,
            'status' => ProductStatus::Active,
        ]);

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Style',
        ]);

        expect($option->product->id)->toBe($product->id);
    });

    describe('Scopes', function (): void {
        it('can filter visible options', function (): void {
            $product = Product::create([
                'name' => 'Visible Options Product',
                'price' => 7000,
                'status' => ProductStatus::Active,
            ]);

            Option::create(['product_id' => $product->id, 'name' => 'Visible', 'is_visible' => true]);
            Option::create(['product_id' => $product->id, 'name' => 'Hidden', 'is_visible' => false]);

            expect(Option::where('product_id', $product->id)->visible()->count())->toBe(1);
        });

        it('can order options by position', function (): void {
            $product = Product::create([
                'name' => 'Ordered Options Product',
                'price' => 8000,
                'status' => ProductStatus::Active,
            ]);

            Option::create(['product_id' => $product->id, 'name' => 'Second', 'position' => 2]);
            Option::create(['product_id' => $product->id, 'name' => 'First', 'position' => 1]);
            Option::create(['product_id' => $product->id, 'name' => 'Third', 'position' => 3]);

            $ordered = Option::where('product_id', $product->id)->ordered()->get();

            expect($ordered->first()->name)->toBe('First');
        });
    });

    describe('Deletion', function (): void {
        it('deletes option values when option is deleted', function (): void {
            $product = Product::create([
                'name' => 'Delete Option Product',
                'price' => 9000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Delete Me',
            ]);

            $value1 = OptionValue::create(['option_id' => $option->id, 'name' => 'Value 1']);
            $value2 = OptionValue::create(['option_id' => $option->id, 'name' => 'Value 2']);

            $optionId = $option->id;
            $option->delete();

            expect(OptionValue::where('option_id', $optionId)->count())->toBe(0);
        });
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

    it('has correct default attributes', function (): void {
        $product = Product::create([
            'name' => 'Default OptionValue Product',
            'price' => 7000,
            'status' => ProductStatus::Active,
        ]);

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Size',
        ]);

        $value = OptionValue::create([
            'option_id' => $option->id,
            'name' => 'Large',
        ]);

        expect($value->position)->toBe(0);
    });

    describe('Swatch Helpers', function (): void {
        it('checks if has color swatch', function (): void {
            $product = Product::create([
                'name' => 'Swatch Product',
                'price' => 8000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
            ]);

            $withColor = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Red',
                'swatch_color' => '#FF0000',
            ]);

            $withoutColor = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Blue',
            ]);

            expect($withColor->hasColorSwatch())->toBeTrue()
                ->and($withoutColor->hasColorSwatch())->toBeFalse();
        });

        it('checks if has image swatch', function (): void {
            $product = Product::create([
                'name' => 'Image Swatch Product',
                'price' => 9000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Pattern',
            ]);

            $withImage = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Stripes',
                'swatch_image' => '/images/stripes.jpg',
            ]);

            $withoutImage = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Dots',
            ]);

            expect($withImage->hasImageSwatch())->toBeTrue()
                ->and($withoutImage->hasImageSwatch())->toBeFalse();
        });

        it('gets swatch style for color', function (): void {
            $product = Product::create([
                'name' => 'Style Color Product',
                'price' => 10000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
            ]);

            $value = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Green',
                'swatch_color' => '#00FF00',
            ]);

            expect($value->getSwatchStyle())->toBe('background-color: #00FF00');
        });

        it('gets swatch style for image', function (): void {
            $product = Product::create([
                'name' => 'Style Image Product',
                'price' => 11000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Pattern',
            ]);

            $value = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Checkered',
                'swatch_image' => '/images/check.png',
            ]);

            expect($value->getSwatchStyle())->toBe("background-image: url('/images/check.png')");
        });

        it('returns null swatch style when no swatch', function (): void {
            $product = Product::create([
                'name' => 'No Swatch Product',
                'price' => 12000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
            ]);

            $value = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'XL',
            ]);

            expect($value->getSwatchStyle())->toBeNull();
        });
    });

    describe('Relationships', function (): void {
        it('can have variants', function (): void {
            $product = Product::create([
                'name' => 'Variants Relationship Product',
                'price' => 13000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
            ]);

            $value = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Small',
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Relationship Variant',
                'sku' => 'VAR-REL-' . uniqid(),
            ]);

            $variant->optionValues()->attach($value->id);

            expect($value->variants)->toHaveCount(1);
        });
    });

    describe('Scopes', function (): void {
        it('can order by position', function (): void {
            $product = Product::create([
                'name' => 'Ordered Values Product',
                'price' => 14000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Size',
            ]);

            OptionValue::create(['option_id' => $option->id, 'name' => 'Large', 'position' => 3]);
            OptionValue::create(['option_id' => $option->id, 'name' => 'Small', 'position' => 1]);
            OptionValue::create(['option_id' => $option->id, 'name' => 'Medium', 'position' => 2]);

            $ordered = OptionValue::where('option_id', $option->id)->ordered()->get();

            expect($ordered->first()->name)->toBe('Small');
        });
    });

    describe('Deletion', function (): void {
        it('detaches variants when option value is deleted', function (): void {
            $product = Product::create([
                'name' => 'Delete Value Product',
                'price' => 15000,
                'status' => ProductStatus::Active,
            ]);

            $option = Option::create([
                'product_id' => $product->id,
                'name' => 'Color',
            ]);

            $value = OptionValue::create([
                'option_id' => $option->id,
                'name' => 'Yellow',
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Delete Value Variant',
                'sku' => 'DEL-VALUE-' . uniqid(),
            ]);

            $variant->optionValues()->attach($value->id);
            $valueId = $value->id;

            $value->delete();

            expect(\Illuminate\Support\Facades\DB::table(config('products.tables.variant_options', 'product_variant_options'))
                ->where('option_value_id', $valueId)->count())->toBe(0);
        });
    });
});
