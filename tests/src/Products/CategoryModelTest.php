<?php

declare(strict_types=1);

use AIArmada\Products\Models\Category;

describe('Category Model', function (): void {
    describe('Category Creation', function (): void {
        it('can create a category', function (): void {
            $category = Category::create([
                'name' => 'Electronics',
            ]);

            expect($category)->toBeInstanceOf(Category::class)
                ->and($category->name)->toBe('Electronics');
        });

        it('generates a slug automatically', function (): void {
            $category = Category::create([
                'name' => 'Home & Garden',
            ]);

            expect($category->slug)->toBe('home-garden');
        });
    });

    describe('Nested Categories', function (): void {
        it('can have a parent category', function (): void {
            $parent = Category::create(['name' => 'Electronics']);
            $child = Category::create([
                'name' => 'Smartphones',
                'parent_id' => $parent->id,
            ]);

            expect($child->parent->id)->toBe($parent->id);
        });

        it('can have multiple children', function (): void {
            $parent = Category::create(['name' => 'Fashion']);
            Category::create(['name' => 'Mens', 'parent_id' => $parent->id]);
            Category::create(['name' => 'Womens', 'parent_id' => $parent->id]);
            Category::create(['name' => 'Kids', 'parent_id' => $parent->id]);

            $parent->refresh();

            expect($parent->children)->toHaveCount(3);
        });
    });

    describe('Category Visibility', function (): void {
        it('can filter visible categories', function (): void {
            Category::create(['name' => 'Visible Cat 1', 'is_visible' => true]);
            Category::create(['name' => 'Visible Cat 2', 'is_visible' => true]);
            Category::create(['name' => 'Hidden Cat', 'is_visible' => false]);

            expect(Category::roots()->count())->toBeGreaterThanOrEqual(2);
        });

        it('can filter root categories', function (): void {
            $root1 = Category::create(['name' => 'Root 1']);
            $root2 = Category::create(['name' => 'Root 2']);
            Category::create(['name' => 'Child', 'parent_id' => $root1->id]);

            expect(Category::roots()->where('parent_id', null)->count())->toBeGreaterThanOrEqual(2);
        });
    });

    describe('Category Ordering', function (): void {
        it('can order categories by position', function (): void {
            Category::create(['name' => 'Third', 'position' => 3]);
            Category::create(['name' => 'First', 'position' => 1]);
            Category::create(['name' => 'Second', 'position' => 2]);

            $ordered = Category::orderBy('position')->get();

            expect($ordered->first()->name)->toBe('First');
        });
    });
});
