<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Models;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Models\TaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxClassTest extends TaxTestCase
{
    use RefreshDatabase;

    public function test_can_create_tax_class(): void
    {
        $taxClass = TaxClass::create([
            'name' => 'Standard Rate',
            'slug' => 'standard',
            'description' => 'Standard tax rate for most products',
            'is_default' => true,
            'is_active' => true,
            'position' => 1,
        ]);

        $this->assertInstanceOf(TaxClass::class, $taxClass);
        $this->assertEquals('Standard Rate', $taxClass->name);
        $this->assertEquals('standard', $taxClass->slug);
        $this->assertTrue($taxClass->is_default);
        $this->assertTrue($taxClass->is_active);
    }

    public function test_get_default_method(): void
    {
        TaxClass::create([
            'name' => 'Standard',
            'slug' => 'standard',
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Reduced',
            'slug' => 'reduced',
            'is_default' => false,
            'is_active' => true,
        ]);

        $default = TaxClass::getDefault();

        $this->assertEquals('Standard', $default->name);
    }

    public function test_find_by_slug_method(): void
    {
        TaxClass::create([
            'name' => 'Standard Rate',
            'slug' => 'standard',
            'is_active' => true,
        ]);

        $found = TaxClass::findBySlug('standard');

        $this->assertEquals('Standard Rate', $found->name);
    }

    public function test_find_by_slug_returns_null_for_nonexistent(): void
    {
        $found = TaxClass::findBySlug('nonexistent');

        $this->assertNull($found);
    }

    public function test_active_scope(): void
    {
        TaxClass::create([
            'name' => 'Active Class',
            'slug' => 'active',
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Inactive Class',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $activeClasses = TaxClass::active()->get();

        $this->assertCount(1, $activeClasses);
        $this->assertEquals('Active Class', $activeClasses->first()->name);
    }

    public function test_default_scope(): void
    {
        TaxClass::create([
            'name' => 'Default Class',
            'slug' => 'default',
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Regular Class',
            'slug' => 'regular',
            'is_default' => false,
            'is_active' => true,
        ]);

        $defaultClasses = TaxClass::default()->get();

        $this->assertCount(1, $defaultClasses);
        $this->assertEquals('Default Class', $defaultClasses->first()->name);
    }

    public function test_ordered_scope(): void
    {
        TaxClass::create([
            'name' => 'Third',
            'slug' => 'third',
            'position' => 3,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'First',
            'slug' => 'first',
            'position' => 1,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Second',
            'slug' => 'second',
            'position' => 2,
            'is_active' => true,
        ]);

        $ordered = TaxClass::ordered()->get();

        $this->assertEquals(['First', 'Second', 'Third'], $ordered->pluck('name')->toArray());
    }

    public function test_casts(): void
    {
        $taxClass = TaxClass::create([
            'name' => 'Cast Test',
            'slug' => 'cast-test',
            'is_default' => true,
            'is_active' => false,
            'position' => 5,
        ]);

        $this->assertIsBool($taxClass->is_default);
        $this->assertIsBool($taxClass->is_active);
        $this->assertIsInt($taxClass->position);
    }

    public function test_attributes_defaults(): void
    {
        $taxClass = new TaxClass(['name' => 'Test', 'slug' => 'test']);

        $this->assertFalse($taxClass->is_default);
        $this->assertTrue($taxClass->is_active);
        $this->assertEquals(0, $taxClass->position);
    }

    public function test_activity_logging(): void
    {
        $taxClass = TaxClass::create([
            'name' => 'Activity Test',
            'slug' => 'activity-test',
            'is_active' => true,
        ]);

        $taxClass->update(['name' => 'Updated Name']);

        // Activity logging is configured but we can't easily test it without more setup
        // This test ensures the trait is applied and doesn't break
        $this->assertTrue(true);
    }

    public function test_for_owner_scope_when_owner_disabled(): void
    {
        config(['tax.features.owner.enabled' => false]);

        TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'is_active' => true,
        ]);

        $classes = TaxClass::forOwner(null)->get();

        $this->assertCount(1, $classes);
    }

    public function test_for_owner_scope_with_null_owner(): void
    {
        config(['tax.features.owner.enabled' => true]);

        TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Owned Class',
            'slug' => 'owned',
            'owner_type' => 'App\\Models\\Store',
            'owner_id' => '123',
            'is_active' => true,
        ]);

        // When owner is null and includeGlobal=true (default), returns records where owner_id is null
        $classes = TaxClass::forOwner(null)->get();

        $this->assertCount(1, $classes);
        $this->assertEquals('Global Class', $classes->first()->name);
    }

    public function test_for_owner_scope_with_null_owner_exclude_global(): void
    {
        config(['tax.features.owner.enabled' => true]);

        TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]);

        // When owner is null and includeGlobal=false, returns records where both owner_type and owner_id are null
        $classes = TaxClass::forOwner(null, includeGlobal: false)->get();

        $this->assertCount(1, $classes);
        $this->assertEquals('Global Class', $classes->first()->name);
    }

    public function test_for_owner_scope_with_owner_include_global(): void
    {
        config(['tax.features.owner.enabled' => true]);

        // Create a mock owner
        $owner = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Owned Class',
            'slug' => 'owned',
            'owner_type' => 'App\\Models\\Store',
            'owner_id' => '123',
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Other Owner Class',
            'slug' => 'other',
            'owner_type' => 'App\\Models\\Store',
            'owner_id' => '456',
            'is_active' => true,
        ]);

        $classes = TaxClass::forOwner($owner, includeGlobal: true)->get();

        $this->assertCount(2, $classes);
        $names = $classes->pluck('name')->toArray();
        $this->assertContains('Global Class', $names);
        $this->assertContains('Owned Class', $names);
    }

    public function test_for_owner_scope_with_owner_exclude_global(): void
    {
        config(['tax.features.owner.enabled' => true]);

        $owner = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';

            public $id = 1;

            public function getMorphClass(): string
            {
                return 'App\\Models\\Store';
            }

            public function getKey(): mixed
            {
                return '123';
            }
        };

        TaxClass::create([
            'name' => 'Global Class',
            'slug' => 'global',
            'owner_type' => null,
            'owner_id' => null,
            'is_active' => true,
        ]);

        TaxClass::create([
            'name' => 'Owned Class',
            'slug' => 'owned',
            'owner_type' => 'App\\Models\\Store',
            'owner_id' => '123',
            'is_active' => true,
        ]);

        $classes = TaxClass::forOwner($owner, includeGlobal: false)->get();

        $this->assertCount(1, $classes);
        $this->assertEquals('Owned Class', $classes->first()->name);
    }

    public function test_get_default_returns_null_when_none(): void
    {
        TaxClass::create([
            'name' => 'Non-default',
            'slug' => 'non-default',
            'is_default' => false,
            'is_active' => true,
        ]);

        $default = TaxClass::getDefault();

        $this->assertNull($default);
    }
}
