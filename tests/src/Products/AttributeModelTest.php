<?php

declare(strict_types=1);

use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Product;

describe('Attribute Model', function (): void {
    describe('Attribute Creation', function (): void {
        it('can create an attribute', function (): void {
            $attribute = Attribute::create([
                'name' => 'Color',
                'code' => 'color',
                'type' => AttributeType::Select,
                'is_required' => false,
                'is_filterable' => true,
            ]);

            expect($attribute)->toBeInstanceOf(Attribute::class)
                ->and($attribute->name)->toBe('Color')
                ->and($attribute->code)->toBe('color')
                ->and($attribute->type)->toBe(AttributeType::Select);
        });

        it('has correct default attributes', function (): void {
            $attribute = Attribute::create([
                'name' => 'Default Attribute',
                'code' => 'default_attr',
                'type' => AttributeType::Text,
            ]);

            expect($attribute->is_required)->toBeFalse()
                ->and($attribute->is_filterable)->toBeFalse()
                ->and($attribute->is_searchable)->toBeFalse()
                ->and($attribute->is_comparable)->toBeFalse()
                ->and($attribute->is_visible_on_front)->toBeTrue()
                ->and($attribute->is_visible_on_admin)->toBeTrue()
                ->and($attribute->position)->toBe(0);
        });

        it('can have multiple values for a product', function (): void {
            $product = Product::factory()->create();
            $attribute = Attribute::create([
                'name' => 'Size',
                'code' => 'size',
                'type' => AttributeType::Select,
                'is_required' => true,
            ]);

            $attribute->values()->create([
                'attributable_type' => Product::class,
                'attributable_id' => $product->id,
                'value' => 'Small',
            ]);
            $attribute->values()->create([
                'attributable_type' => Product::class,
                'attributable_id' => $product->id,
                'value' => 'Medium',
            ]);

            $attribute->refresh();

            expect($attribute->values)->toHaveCount(2);
        });
    });

    describe('Value Casting', function (): void {
        it('can cast text value', function (): void {
            $attribute = Attribute::create([
                'name' => 'Cast Text',
                'code' => 'cast_text',
                'type' => AttributeType::Text,
            ]);

            $result = $attribute->castValue('Hello World');

            expect($result)->toBe('Hello World');
        });

        it('can cast number value', function (): void {
            $attribute = Attribute::create([
                'name' => 'Cast Number',
                'code' => 'cast_number',
                'type' => AttributeType::Number,
            ]);

            $result = $attribute->castValue('42');

            expect($result)->toBe(42.0); // Numbers are cast to float
        });

        it('can cast boolean value', function (): void {
            $attribute = Attribute::create([
                'name' => 'Cast Boolean',
                'code' => 'cast_boolean',
                'type' => AttributeType::Boolean,
            ]);

            expect($attribute->castValue('1'))->toBeTrue()
                ->and($attribute->castValue('0'))->toBeFalse();
        });

        it('can serialize text value', function (): void {
            $attribute = Attribute::create([
                'name' => 'Serialize Text',
                'code' => 'serialize_text',
                'type' => AttributeType::Text,
            ]);

            $result = $attribute->serializeValue('Test String');

            expect($result)->toBe('Test String');
        });

        it('can serialize number value', function (): void {
            $attribute = Attribute::create([
                'name' => 'Serialize Number',
                'code' => 'serialize_number',
                'type' => AttributeType::Number,
            ]);

            $result = $attribute->serializeValue(123);

            expect($result)->toBe('123');
        });
    });

    describe('Validation Rules', function (): void {
        it('gets validation rules with required', function (): void {
            $attribute = Attribute::create([
                'name' => 'Required Attr',
                'code' => 'required_attr',
                'type' => AttributeType::Text,
                'is_required' => true,
            ]);

            $rules = $attribute->getValidationRules();

            expect($rules)->toContain('required');
        });

        it('gets validation rules with nullable', function (): void {
            $attribute = Attribute::create([
                'name' => 'Nullable Attr',
                'code' => 'nullable_attr',
                'type' => AttributeType::Text,
                'is_required' => false,
            ]);

            $rules = $attribute->getValidationRules();

            expect($rules)->toContain('nullable');
        });

        it('includes custom validation rules', function (): void {
            $attribute = Attribute::create([
                'name' => 'Custom Validation',
                'code' => 'custom_validation',
                'type' => AttributeType::Text,
                'validation' => ['max:255', 'alpha_num'],
            ]);

            $rules = $attribute->getValidationRules();

            expect($rules)->toContain('max:255')
                ->and($rules)->toContain('alpha_num');
        });
    });

    describe('Options', function (): void {
        it('checks if attribute has options', function (): void {
            $withOptions = Attribute::create([
                'name' => 'With Options',
                'code' => 'with_options',
                'type' => AttributeType::Select,
                'options' => ['Red', 'Blue', 'Green'],
            ]);

            $withoutOptions = Attribute::create([
                'name' => 'Without Options',
                'code' => 'without_options',
                'type' => AttributeType::Text,
            ]);

            expect($withOptions->hasOptions())->toBeTrue()
                ->and($withoutOptions->hasOptions())->toBeFalse();
        });

        it('gets options as array for simple options', function (): void {
            $attribute = Attribute::create([
                'name' => 'Simple Options',
                'code' => 'simple_options',
                'type' => AttributeType::Select,
                'options' => ['Small', 'Medium', 'Large'],
            ]);

            $optionsArray = $attribute->getOptionsArray();

            expect($optionsArray)->toHaveKey('Small')
                ->and($optionsArray)->toHaveKey('Medium')
                ->and($optionsArray)->toHaveKey('Large');
        });

        it('gets options as array for value/label options', function (): void {
            $attribute = Attribute::create([
                'name' => 'Value Label Options',
                'code' => 'value_label_options',
                'type' => AttributeType::Select,
                'options' => [
                    ['value' => 'sm', 'label' => 'Small'],
                    ['value' => 'md', 'label' => 'Medium'],
                    ['value' => 'lg', 'label' => 'Large'],
                ],
            ]);

            $optionsArray = $attribute->getOptionsArray();

            expect($optionsArray)->toHaveKey('sm')
                ->and($optionsArray['sm'])->toBe('Small')
                ->and($optionsArray)->toHaveKey('md')
                ->and($optionsArray['md'])->toBe('Medium');
        });

        it('returns empty array when no options', function (): void {
            $attribute = Attribute::create([
                'name' => 'No Options',
                'code' => 'no_options',
                'type' => AttributeType::Text,
            ]);

            expect($attribute->getOptionsArray())->toBeEmpty();
        });
    });

    describe('Attribute Relationships', function (): void {
        it('belongs to attribute groups', function (): void {
            $group = AttributeGroup::create([
                'name' => 'Product Details',
                'code' => 'product_details',
            ]);

            $attribute = Attribute::create([
                'name' => 'Weight',
                'code' => 'weight',
                'type' => AttributeType::Number,
            ]);

            $pivotId = \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')->insert([
                'id' => $pivotId,
                'attribute_id' => $attribute->id,
                'attribute_group_id' => $group->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $pivotRecord = \Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')->where('id', $pivotId)->first();
            expect($pivotRecord)->not->toBeNull();

            $group->load('groupAttributes');

            expect($group->groupAttributes)->toHaveCount(1)
                ->and($group->groupAttributes->first()->name)->toBe('Weight');
        });

        it('belongs to attribute sets', function (): void {
            $set = AttributeSet::create([
                'name' => 'T-Shirt Attributes',
                'code' => 'tshirt_attributes',
            ]);

            $attribute = Attribute::create([
                'name' => 'Material',
                'code' => 'material',
                'type' => AttributeType::Text,
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_set')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_set_id' => $set->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $set->load('setAttributes');

            expect($set->setAttributes)->toHaveCount(1)
                ->and($set->setAttributes->first()->name)->toBe('Material');
        });
    });

    describe('Attribute Scopes', function (): void {
        it('can filter filterable attributes', function (): void {
            Attribute::create(['name' => 'Filterable', 'code' => 'filt', 'type' => AttributeType::Select, 'is_filterable' => true]);
            Attribute::create(['name' => 'Not Filterable', 'code' => 'no_filt', 'type' => AttributeType::Text, 'is_filterable' => false]);

            expect(Attribute::filterable()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter searchable attributes', function (): void {
            Attribute::create(['name' => 'Searchable', 'code' => 'search', 'type' => AttributeType::Text, 'is_searchable' => true]);
            Attribute::create(['name' => 'Not Searchable', 'code' => 'no_search', 'type' => AttributeType::Text, 'is_searchable' => false]);

            expect(Attribute::searchable()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter comparable attributes', function (): void {
            Attribute::create(['name' => 'Comparable', 'code' => 'comp', 'type' => AttributeType::Text, 'is_comparable' => true]);
            Attribute::create(['name' => 'Not Comparable', 'code' => 'no_comp', 'type' => AttributeType::Text, 'is_comparable' => false]);

            expect(Attribute::comparable()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter visible on front attributes', function (): void {
            Attribute::create(['name' => 'Visible Front', 'code' => 'vis_front', 'type' => AttributeType::Text, 'is_visible_on_front' => true]);
            Attribute::create(['name' => 'Hidden Front', 'code' => 'hid_front', 'type' => AttributeType::Text, 'is_visible_on_front' => false]);

            expect(Attribute::visibleOnFront()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can order attributes by position', function (): void {
            Attribute::create(['name' => 'Position 3', 'code' => 'pos3', 'type' => AttributeType::Text, 'position' => 3]);
            Attribute::create(['name' => 'Position 1', 'code' => 'pos1', 'type' => AttributeType::Text, 'position' => 1]);
            Attribute::create(['name' => 'Position 2', 'code' => 'pos2', 'type' => AttributeType::Text, 'position' => 2]);

            $ordered = Attribute::ordered()->get();

            expect($ordered->first()->name)->toBe('Position 1');
        });
    });

    describe('Deletion', function (): void {
        it('deletes attribute values when attribute is deleted', function (): void {
            $attribute = Attribute::create([
                'name' => 'Delete Values Attr',
                'code' => 'delete_values_attr',
                'type' => AttributeType::Text,
            ]);

            $product = Product::factory()->create();

            $attribute->values()->create([
                'attributable_type' => Product::class,
                'attributable_id' => $product->id,
                'value' => 'To Be Deleted',
            ]);

            $attributeId = $attribute->id;
            $attribute->delete();

            expect(\AIArmada\Products\Models\AttributeValue::where('attribute_id', $attributeId)->count())->toBe(0);
        });

        it('detaches from groups and sets when deleted', function (): void {
            $group = AttributeGroup::create(['name' => 'Delete Group', 'code' => 'delete_group']);
            $set = AttributeSet::create(['name' => 'Delete Set', 'code' => 'delete_set']);

            $attribute = Attribute::create([
                'name' => 'Delete Attr',
                'code' => 'delete_attr',
                'type' => AttributeType::Text,
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_group_id' => $group->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_set')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_set_id' => $set->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $attributeId = $attribute->id;
            $attribute->delete();

            expect(\Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')
                ->where('attribute_id', $attributeId)->count())->toBe(0);
            expect(\Illuminate\Support\Facades\DB::table('product_attribute_attribute_set')
                ->where('attribute_id', $attributeId)->count())->toBe(0);
        });
    });
});

describe('AttributeGroup Model', function (): void {
    describe('Creation', function (): void {
        it('can create an attribute group', function (): void {
            $group = AttributeGroup::create([
                'name' => 'General',
                'code' => 'general',
            ]);

            expect($group)->toBeInstanceOf(AttributeGroup::class)
                ->and($group->name)->toBe('General');
        });

        it('has correct default attributes', function (): void {
            $group = AttributeGroup::create([
                'name' => 'Default Group',
                'code' => 'default_group',
            ]);

            expect($group->position)->toBe(0)
                ->and($group->is_visible)->toBeTrue();
        });
    });

    describe('Scopes', function (): void {
        it('can filter visible groups', function (): void {
            AttributeGroup::create(['name' => 'Visible Group', 'code' => 'vis_grp', 'is_visible' => true]);
            AttributeGroup::create(['name' => 'Hidden Group', 'code' => 'hid_grp', 'is_visible' => false]);

            expect(AttributeGroup::visible()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can order groups by position', function (): void {
            AttributeGroup::create(['name' => 'Group 3', 'code' => 'grp3', 'position' => 3]);
            AttributeGroup::create(['name' => 'Group 1', 'code' => 'grp1', 'position' => 1]);

            $ordered = AttributeGroup::ordered()->get();

            expect($ordered->first()->name)->toBe('Group 1');
        });
    });

    describe('Deletion', function (): void {
        it('detaches attributes and attribute sets when deleted', function (): void {
            $group = AttributeGroup::create(['name' => 'Delete Grp', 'code' => 'del_grp']);

            $attribute = Attribute::create([
                'name' => 'Group Attr',
                'code' => 'group_attr',
                'type' => AttributeType::Text,
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_group_id' => $group->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $groupId = $group->id;
            $group->delete();

            expect(\Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')
                ->where('attribute_group_id', $groupId)->count())->toBe(0);
        });
    });
});

describe('AttributeSet Model', function (): void {
    describe('Creation', function (): void {
        it('can create an attribute set', function (): void {
            $set = AttributeSet::create([
                'name' => 'Default Set',
                'code' => 'default_set',
            ]);

            expect($set)->toBeInstanceOf(AttributeSet::class)
                ->and($set->name)->toBe('Default Set');
        });
    });

    describe('Default Set', function (): void {
        it('can set as default', function (): void {
            $set1 = AttributeSet::create(['name' => 'Set 1', 'code' => 'set1', 'is_default' => true]);
            $set2 = AttributeSet::create(['name' => 'Set 2', 'code' => 'set2', 'is_default' => false]);

            $set2->setAsDefault();

            $set1->refresh();
            $set2->refresh();

            expect($set1->is_default)->toBeFalse()
                ->and($set2->is_default)->toBeTrue();
        });
    });

    describe('Scopes', function (): void {
        it('can filter default sets', function (): void {
            AttributeSet::create(['name' => 'Default', 'code' => 'def_set', 'is_default' => true]);
            AttributeSet::create(['name' => 'Not Default', 'code' => 'not_def', 'is_default' => false]);

            expect(AttributeSet::default()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can order sets by position', function (): void {
            AttributeSet::create(['name' => 'Set 2', 'code' => 'ord_set2', 'position' => 2]);
            AttributeSet::create(['name' => 'Set 1', 'code' => 'ord_set1', 'position' => 1]);

            $ordered = AttributeSet::ordered()->get();

            expect($ordered->first()->name)->toBe('Set 1');
        });
    });

    describe('Grouped Attributes', function (): void {
        it('can get grouped attributes', function (): void {
            $set = AttributeSet::create(['name' => 'Grouped Set', 'code' => 'grouped_set']);
            $group = AttributeGroup::create(['name' => 'Grouped Group', 'code' => 'grouped_group']);

            $attribute = Attribute::create([
                'name' => 'Grouped Attr',
                'code' => 'grouped_attr',
                'type' => AttributeType::Text,
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_set')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_set_id' => $set->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_group_attribute_set')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_group_id' => $group->id,
                'attribute_set_id' => $set->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_group')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_group_id' => $group->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $groupedAttributes = $set->getGroupedAttributes();

            expect($groupedAttributes)->toHaveCount(1);
        });
    });

    describe('Deletion', function (): void {
        it('detaches attributes and groups when deleted', function (): void {
            $set = AttributeSet::create(['name' => 'Delete Set', 'code' => 'del_set']);

            $attribute = Attribute::create([
                'name' => 'Set Attr',
                'code' => 'set_attr',
                'type' => AttributeType::Text,
            ]);

            \Illuminate\Support\Facades\DB::table('product_attribute_attribute_set')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'attribute_id' => $attribute->id,
                'attribute_set_id' => $set->id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $setId = $set->id;
            $set->delete();

            expect(\Illuminate\Support\Facades\DB::table('product_attribute_attribute_set')
                ->where('attribute_set_id', $setId)->count())->toBe(0);
        });
    });
});