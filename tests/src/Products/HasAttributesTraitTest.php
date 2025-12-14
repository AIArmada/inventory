<?php

declare(strict_types=1);

use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Product;

describe('HasAttributes Trait', function (): void {
    describe('Custom Attribute Management', function (): void {
        it('can set a custom attribute value', function (): void {
            $attribute = Attribute::create([
                'code' => 'brand',
                'name' => 'Brand',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Branded Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = $product->setCustomAttribute('brand', 'Nike');

            expect($attributeValue)->toBeInstanceOf(AttributeValue::class)
                ->and($attributeValue->value)->toBe('Nike');
        });

        it('can get a custom attribute value', function (): void {
            $attribute = Attribute::create([
                'code' => 'material',
                'name' => 'Material',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Material Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('material', 'Cotton');

            $value = $product->getCustomAttribute('material');

            expect($value)->toBe('Cotton');
        });

        it('returns null for non-existent attribute', function (): void {
            $product = Product::create([
                'name' => 'No Attribute Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]);

            $value = $product->getCustomAttribute('non_existent');

            expect($value)->toBeNull();
        });

        it('can set multiple custom attributes at once', function (): void {
            $brandAttr = Attribute::create([
                'code' => 'brand_multi',
                'name' => 'Brand',
                'type' => AttributeType::Text,
            ]);

            $colorAttr = Attribute::create([
                'code' => 'color_multi',
                'name' => 'Color',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Multi Attribute Product',
                'price' => 4000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttributes([
                'brand_multi' => 'Adidas',
                'color_multi' => 'Blue',
            ]);

            expect($product->getCustomAttribute('brand_multi'))->toBe('Adidas')
                ->and($product->getCustomAttribute('color_multi'))->toBe('Blue');
        });

        it('can get all custom attributes as array', function (): void {
            $attr1 = Attribute::create([
                'code' => 'size_arr',
                'name' => 'Size',
                'type' => AttributeType::Text,
            ]);

            $attr2 = Attribute::create([
                'code' => 'weight_arr',
                'name' => 'Weight',
                'type' => AttributeType::Number,
            ]);

            $product = Product::create([
                'name' => 'Array Attribute Product',
                'price' => 5000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('size_arr', 'Large');
            $product->setCustomAttribute('weight_arr', '100');

            $attributes = $product->getCustomAttributesArray();

            expect($attributes)->toHaveKey('size_arr')
                ->and($attributes)->toHaveKey('weight_arr');
        });

        it('can check if model has custom attribute', function (): void {
            $attribute = Attribute::create([
                'code' => 'has_check',
                'name' => 'Has Check',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Has Check Product',
                'price' => 6000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->hasCustomAttribute('has_check'))->toBeFalse();

            $product->setCustomAttribute('has_check', 'Yes');

            expect($product->hasCustomAttribute('has_check'))->toBeTrue();
        });

        it('can remove a custom attribute', function (): void {
            $attribute = Attribute::create([
                'code' => 'removable',
                'name' => 'Removable',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Removable Attribute Product',
                'price' => 7000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('removable', 'To Be Removed');
            expect($product->hasCustomAttribute('removable'))->toBeTrue();

            $result = $product->removeCustomAttribute('removable');

            expect($result)->toBeTrue()
                ->and($product->hasCustomAttribute('removable'))->toBeFalse();
        });

        it('can clear all custom attributes', function (): void {
            $attr1 = Attribute::create([
                'code' => 'clear1',
                'name' => 'Clear 1',
                'type' => AttributeType::Text,
            ]);

            $attr2 = Attribute::create([
                'code' => 'clear2',
                'name' => 'Clear 2',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Clear Attributes Product',
                'price' => 8000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('clear1', 'Value 1');
            $product->setCustomAttribute('clear2', 'Value 2');

            $deleted = $product->clearCustomAttributes();

            expect($deleted)->toBe(2)
                ->and($product->attributeValues()->count())->toBe(0);
        });
    });

    describe('Locale Support', function (): void {
        it('can set attribute with locale', function (): void {
            $attribute = Attribute::create([
                'code' => 'description_locale',
                'name' => 'Description',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Locale Product',
                'price' => 9000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('description_locale', 'English Description', 'en');
            $product->setCustomAttribute('description_locale', 'Malay Description', 'ms');

            expect($product->getCustomAttribute('description_locale', 'en'))->toBe('English Description')
                ->and($product->getCustomAttribute('description_locale', 'ms'))->toBe('Malay Description');
        });

        it('can get attributes for specific locale', function (): void {
            $attribute = Attribute::create([
                'code' => 'title_locale',
                'name' => 'Title',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Locale Array Product',
                'price' => 10000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('title_locale', 'English Title', 'en');

            $attributes = $product->getCustomAttributesArray('en');

            expect($attributes)->toHaveKey('title_locale')
                ->and($attributes['title_locale'])->toBe('English Title');
        });
    });

    describe('Filterable Attributes', function (): void {
        it('can get filterable custom attributes', function (): void {
            $filterableAttr = Attribute::create([
                'code' => 'filterable_attr',
                'name' => 'Filterable',
                'type' => AttributeType::Text,
                'is_filterable' => true,
            ]);

            $nonFilterableAttr = Attribute::create([
                'code' => 'non_filterable_attr',
                'name' => 'Non Filterable',
                'type' => AttributeType::Text,
                'is_filterable' => false,
            ]);

            $product = Product::create([
                'name' => 'Filterable Product',
                'price' => 11000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('filterable_attr', 'Yes');
            $product->setCustomAttribute('non_filterable_attr', 'No');

            $filterableAttributes = $product->getFilterableCustomAttributes();

            expect($filterableAttributes)->toHaveKey('filterable_attr')
                ->and($filterableAttributes)->not->toHaveKey('non_filterable_attr');
        });
    });

    describe('Visible Attributes', function (): void {
        it('can get visible custom attributes', function (): void {
            $visibleAttr = Attribute::create([
                'code' => 'visible_attr',
                'name' => 'Visible',
                'type' => AttributeType::Text,
                'is_visible_on_front' => true,
            ]);

            $hiddenAttr = Attribute::create([
                'code' => 'hidden_attr',
                'name' => 'Hidden',
                'type' => AttributeType::Text,
                'is_visible_on_front' => false,
            ]);

            $product = Product::create([
                'name' => 'Visible Attr Product',
                'price' => 12000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('visible_attr', 'Show');
            $product->setCustomAttribute('hidden_attr', 'Hide');

            $visibleAttributes = $product->getVisibleCustomAttributes();

            expect($visibleAttributes)->toHaveKey('visible_attr')
                ->and($visibleAttributes)->not->toHaveKey('hidden_attr');
        });
    });

    describe('Comparable Attributes', function (): void {
        it('can get comparable custom attributes', function (): void {
            $comparableAttr = Attribute::create([
                'code' => 'comparable_attr',
                'name' => 'Comparable',
                'type' => AttributeType::Text,
                'is_comparable' => true,
            ]);

            $nonComparableAttr = Attribute::create([
                'code' => 'non_comparable_attr',
                'name' => 'Non Comparable',
                'type' => AttributeType::Text,
                'is_comparable' => false,
            ]);

            $product = Product::create([
                'name' => 'Comparable Product',
                'price' => 13000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('comparable_attr', 'Compare Me');
            $product->setCustomAttribute('non_comparable_attr', 'Dont Compare');

            $comparableAttributes = $product->getComparableCustomAttributes();

            expect($comparableAttributes)->toHaveKey('comparable_attr')
                ->and($comparableAttributes['comparable_attr'])->toHaveKey('label')
                ->and($comparableAttributes['comparable_attr'])->toHaveKey('value')
                ->and($comparableAttributes)->not->toHaveKey('non_comparable_attr');
        });
    });

    describe('Scopes', function (): void {
        it('can filter by custom attribute value', function (): void {
            $attribute = Attribute::create([
                'code' => 'scope_color',
                'name' => 'Color',
                'type' => AttributeType::Text,
            ]);

            $redProduct = Product::create([
                'name' => 'Red Product',
                'price' => 14000,
                'status' => ProductStatus::Active,
            ]);
            $redProduct->setCustomAttribute('scope_color', 'red');

            $blueProduct = Product::create([
                'name' => 'Blue Product',
                'price' => 14000,
                'status' => ProductStatus::Active,
            ]);
            $blueProduct->setCustomAttribute('scope_color', 'blue');

            $redProducts = Product::whereCustomAttribute('scope_color', 'red')->get();

            expect($redProducts)->toHaveCount(1)
                ->and($redProducts->first()->name)->toBe('Red Product');
        });

        it('can filter by multiple custom attributes', function (): void {
            $colorAttr = Attribute::create([
                'code' => 'multi_scope_color',
                'name' => 'Color',
                'type' => AttributeType::Text,
            ]);

            $sizeAttr = Attribute::create([
                'code' => 'multi_scope_size',
                'name' => 'Size',
                'type' => AttributeType::Text,
            ]);

            $product1 = Product::create([
                'name' => 'Red Large',
                'price' => 15000,
                'status' => ProductStatus::Active,
            ]);
            $product1->setCustomAttribute('multi_scope_color', 'red');
            $product1->setCustomAttribute('multi_scope_size', 'large');

            $product2 = Product::create([
                'name' => 'Red Small',
                'price' => 15000,
                'status' => ProductStatus::Active,
            ]);
            $product2->setCustomAttribute('multi_scope_color', 'red');
            $product2->setCustomAttribute('multi_scope_size', 'small');

            $filteredProducts = Product::whereCustomAttributes([
                'multi_scope_color' => 'red',
                'multi_scope_size' => 'large',
            ])->get();

            expect($filteredProducts)->toHaveCount(1)
                ->and($filteredProducts->first()->name)->toBe('Red Large');
        });

        it('can filter by array of values', function (): void {
            $attribute = Attribute::create([
                'code' => 'array_filter',
                'name' => 'Status',
                'type' => AttributeType::Text,
            ]);

            $newProduct = Product::create([
                'name' => 'New Item',
                'price' => 16000,
                'status' => ProductStatus::Active,
            ]);
            $newProduct->setCustomAttribute('array_filter', 'new');

            $saleProduct = Product::create([
                'name' => 'Sale Item',
                'price' => 16000,
                'status' => ProductStatus::Active,
            ]);
            $saleProduct->setCustomAttribute('array_filter', 'sale');

            $oldProduct = Product::create([
                'name' => 'Old Item',
                'price' => 16000,
                'status' => ProductStatus::Active,
            ]);
            $oldProduct->setCustomAttribute('array_filter', 'old');

            $filteredProducts = Product::whereCustomAttribute('array_filter', ['new', 'sale'])->get();

            expect($filteredProducts)->toHaveCount(2);
        });
    });
});

describe('AttributeValue Model', function (): void {
    describe('Relationships', function (): void {
        it('belongs to an attribute', function (): void {
            $attribute = Attribute::create([
                'code' => 'attr_rel',
                'name' => 'Attribute Relation',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'AttrValue Rel Product',
                'price' => 17000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = $product->setCustomAttribute('attr_rel', 'Test Value');

            expect($attributeValue->attribute->id)->toBe($attribute->id);
        });

        it('morphs to attributable model', function (): void {
            $attribute = Attribute::create([
                'code' => 'morph_attr',
                'name' => 'Morph Attribute',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Morph Product',
                'price' => 18000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = $product->setCustomAttribute('morph_attr', 'Morph Value');

            expect($attributeValue->attributable)->toBeInstanceOf(Product::class)
                ->and($attributeValue->attributable->id)->toBe($product->id);
        });
    });

    describe('Typed Value', function (): void {
        it('can get typed value for text', function (): void {
            $attribute = Attribute::create([
                'code' => 'typed_text',
                'name' => 'Typed Text',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Typed Text Product',
                'price' => 19000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = $product->setCustomAttribute('typed_text', 'Hello World');

            expect($attributeValue->typed_value)->toBe('Hello World');
        });

        it('can get typed value for number', function (): void {
            $attribute = Attribute::create([
                'code' => 'typed_number',
                'name' => 'Typed Number',
                'type' => AttributeType::Number,
            ]);

            $product = Product::create([
                'name' => 'Typed Number Product',
                'price' => 20000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = $product->setCustomAttribute('typed_number', 42);

            expect($attributeValue->typed_value)->toBe(42.0); // Numbers are cast to float
        });

        it('can get typed value for boolean', function (): void {
            $attribute = Attribute::create([
                'code' => 'typed_bool',
                'name' => 'Typed Boolean',
                'type' => AttributeType::Boolean,
            ]);

            $product = Product::create([
                'name' => 'Typed Boolean Product',
                'price' => 21000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = $product->setCustomAttribute('typed_bool', true);

            expect($attributeValue->typed_value)->toBeTrue();
        });

        it('can set typed value', function (): void {
            $attribute = Attribute::create([
                'code' => 'set_typed',
                'name' => 'Set Typed',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Set Typed Product',
                'price' => 22000,
                'status' => ProductStatus::Active,
            ]);

            $attributeValue = AttributeValue::create([
                'attribute_id' => $attribute->id,
                'attributable_type' => Product::class,
                'attributable_id' => $product->id,
            ]);

            $attributeValue->setTypedValue('New Typed Value');

            expect($attributeValue->value)->toBe('New Typed Value');
        });
    });

    describe('Scopes', function (): void {
        it('can filter by locale', function (): void {
            $attribute = Attribute::create([
                'code' => 'locale_scope',
                'name' => 'Locale Scope',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Locale Scope Product',
                'price' => 23000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('locale_scope', 'English', 'en');
            $product->setCustomAttribute('locale_scope', 'Malay', 'ms');

            $englishValues = AttributeValue::forLocale('en')->get();
            $malayValues = AttributeValue::forLocale('ms')->get();

            expect($englishValues->where('locale', 'en')->count())->toBeGreaterThanOrEqual(1)
                ->and($malayValues->where('locale', 'ms')->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter by attribute code', function (): void {
            $attr1 = Attribute::create([
                'code' => 'attr_code_scope1',
                'name' => 'Attribute Code 1',
                'type' => AttributeType::Text,
            ]);

            $attr2 = Attribute::create([
                'code' => 'attr_code_scope2',
                'name' => 'Attribute Code 2',
                'type' => AttributeType::Text,
            ]);

            $product = Product::create([
                'name' => 'Attr Code Scope Product',
                'price' => 24000,
                'status' => ProductStatus::Active,
            ]);

            $product->setCustomAttribute('attr_code_scope1', 'Value 1');
            $product->setCustomAttribute('attr_code_scope2', 'Value 2');

            $filteredValues = AttributeValue::forAttribute('attr_code_scope1')->get();

            expect($filteredValues->every(fn ($v) => $v->attribute->code === 'attr_code_scope1'))->toBeTrue();
        });
    });
});
