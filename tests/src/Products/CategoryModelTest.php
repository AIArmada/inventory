<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;

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

        it('has correct default attributes', function (): void {
            $category = Category::create(['name' => 'Default Category']);

            expect($category->position)->toBe(0)
                ->and($category->is_visible)->toBeTrue()
                ->and($category->is_featured)->toBeFalse();
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

        it('can get descendants recursively', function (): void {
            $grandparent = Category::create(['name' => 'Apparel']);
            $parent = Category::create(['name' => 'Shoes', 'parent_id' => $grandparent->id]);
            $child = Category::create(['name' => 'Sneakers', 'parent_id' => $parent->id]);

            $grandparent->load('descendants');

            expect($grandparent->descendants)->toHaveCount(1)
                ->and($grandparent->descendants->first()->descendants)->toHaveCount(1);
        });
    });

    describe('Hierarchy Helpers', function (): void {
        it('can check if category is root', function (): void {
            $root = Category::create(['name' => 'Root Category']);
            $child = Category::create(['name' => 'Child Category', 'parent_id' => $root->id]);

            expect($root->isRoot())->toBeTrue()
                ->and($child->isRoot())->toBeFalse();
        });

        it('can check if category has children', function (): void {
            $withChildren = Category::create(['name' => 'Parent']);
            Category::create(['name' => 'Child', 'parent_id' => $withChildren->id]);

            $withoutChildren = Category::create(['name' => 'Childless']);

            expect($withChildren->hasChildren())->toBeTrue()
                ->and($withoutChildren->hasChildren())->toBeFalse();
        });

        it('can get all ancestors', function (): void {
            $grandparent = Category::create(['name' => 'Grandparent']);
            $parent = Category::create(['name' => 'Parent', 'parent_id' => $grandparent->id]);
            $child = Category::create(['name' => 'Child', 'parent_id' => $parent->id]);

            $ancestors = $child->getAncestors();

            expect($ancestors)->toHaveCount(2)
                ->and($ancestors->first()->name)->toBe('Grandparent')
                ->and($ancestors->last()->name)->toBe('Parent');
        });

        it('can get depth of category in tree', function (): void {
            $root = Category::create(['name' => 'Level 0']);
            $level1 = Category::create(['name' => 'Level 1', 'parent_id' => $root->id]);
            $level2 = Category::create(['name' => 'Level 2', 'parent_id' => $level1->id]);

            expect($root->getDepth())->toBe(0)
                ->and($level1->getDepth())->toBe(1)
                ->and($level2->getDepth())->toBe(2);
        });

        it('can get full path of category names', function (): void {
            $electronics = Category::create(['name' => 'Electronics']);
            $phones = Category::create(['name' => 'Phones', 'parent_id' => $electronics->id]);
            $smartphones = Category::create(['name' => 'Smartphones', 'parent_id' => $phones->id]);

            expect($smartphones->getFullPath())->toBe('Electronics > Phones > Smartphones');
        });

        it('can get full path with custom separator', function (): void {
            $home = Category::create(['name' => 'Home']);
            $living = Category::create(['name' => 'Living Room', 'parent_id' => $home->id]);

            expect($living->getFullPath(' / '))->toBe('Home / Living Room');
        });

        it('can get full slug path', function (): void {
            $clothing = Category::create(['name' => 'Clothing']);
            $mens = Category::create(['name' => 'Mens', 'parent_id' => $clothing->id]);
            $shirts = Category::create(['name' => 'Shirts', 'parent_id' => $mens->id]);

            expect($shirts->getFullSlug())->toBe('clothing/mens/shirts');
        });

        it('can get nested tree structure', function (): void {
            $parent = Category::create(['name' => 'Parent Tree']);
            $child1 = Category::create(['name' => 'Child 1', 'parent_id' => $parent->id]);
            $child2 = Category::create(['name' => 'Child 2', 'parent_id' => $parent->id]);

            $parent->load('children');
            $tree = $parent->getNestedTree();

            expect($tree)->toHaveKey('id')
                ->and($tree)->toHaveKey('name')
                ->and($tree)->toHaveKey('slug')
                ->and($tree)->toHaveKey('children')
                ->and($tree['children'])->toHaveCount(2);
        });
    });

    describe('Product Helpers', function (): void {
        it('can get product count without descendants', function (): void {
            $category = Category::create(['name' => 'Product Count Category']);
            $product1 = Product::create(['name' => 'Product 1', 'price' => 1000, 'status' => ProductStatus::Active]);
            $product2 = Product::create(['name' => 'Product 2', 'price' => 2000, 'status' => ProductStatus::Active]);

            $category->products()->attach([$product1->id, $product2->id]);

            expect($category->getProductCount(false))->toBe(2);
        });

        it('can get product count including descendants', function (): void {
            $parent = Category::create(['name' => 'Parent Products']);
            $child = Category::create(['name' => 'Child Products', 'parent_id' => $parent->id]);

            $parentProduct = Product::create(['name' => 'Parent Product', 'price' => 1000, 'status' => ProductStatus::Active]);
            $childProduct = Product::create(['name' => 'Child Product', 'price' => 2000, 'status' => ProductStatus::Active]);

            $parent->products()->attach($parentProduct->id);
            $child->products()->attach($childProduct->id);

            expect($parent->getProductCount(true))->toBe(2);
        });

        it('can get all products including descendants', function (): void {
            $parent = Category::create(['name' => 'All Products Parent']);
            $child = Category::create(['name' => 'All Products Child', 'parent_id' => $parent->id]);

            $parentProduct = Product::create(['name' => 'Parent P', 'price' => 1000, 'status' => ProductStatus::Active]);
            $childProduct = Product::create(['name' => 'Child P', 'price' => 2000, 'status' => ProductStatus::Active]);

            $parent->products()->attach($parentProduct->id);
            $child->products()->attach($childProduct->id);

            $parent->load('descendants');
            $allProducts = $parent->getAllProducts();

            expect($allProducts)->toHaveCount(2);
        });
    });

    describe('Category Visibility', function (): void {
        it('can filter visible categories', function (): void {
            Category::create(['name' => 'Visible Cat 1', 'is_visible' => true]);
            Category::create(['name' => 'Visible Cat 2', 'is_visible' => true]);
            Category::create(['name' => 'Hidden Cat', 'is_visible' => false]);

            expect(Category::visible()->count())->toBeGreaterThanOrEqual(2);
        });

        it('can filter featured categories', function (): void {
            Category::create(['name' => 'Featured Cat', 'is_featured' => true]);
            Category::create(['name' => 'Not Featured Cat', 'is_featured' => false]);

            expect(Category::featured()->count())->toBeGreaterThanOrEqual(1);
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

        it('can use ordered scope', function (): void {
            Category::create(['name' => 'Z Order', 'position' => 10]);
            Category::create(['name' => 'A Order', 'position' => 1]);

            $ordered = Category::ordered()->get();

            expect($ordered->first()->name)->toBe('A Order');
        });
    });

    describe('Media Collections', function (): void {
        it('registers hero, icon, and banner media collections', function (): void {
            $category = Category::create(['name' => 'Media Category']);

            $mediaCollections = collect($category->getRegisteredMediaCollections());

            expect($mediaCollections->pluck('name'))->toContain('hero', 'icon', 'banner');
        });
    });

    describe('Route Key Name', function (): void {
        it('uses slug as route key', function (): void {
            $category = Category::create(['name' => 'Route Category']);

            expect($category->getRouteKeyName())->toBe('slug');
        });
    });

    describe('Deletion', function (): void {
        it('nullifies parent_id for children when deleted', function (): void {
            $parent = Category::create(['name' => 'Parent to Delete']);
            $child = Category::create(['name' => 'Orphan Child', 'parent_id' => $parent->id]);

            $childId = $child->id;
            $parent->delete();

            $orphan = Category::find($childId);
            expect($orphan->parent_id)->toBeNull();
        });

        it('detaches products when category is deleted', function (): void {
            $category = Category::create(['name' => 'Delete Products Category']);
            $product = Product::create(['name' => 'Delete Cat Product', 'price' => 1000, 'status' => ProductStatus::Active]);

            $category->products()->attach($product->id);
            $categoryId = $category->id;

            $category->delete();

            expect(Illuminate\Support\Facades\DB::table(config('products.tables.category_product', 'category_product'))
                ->where('category_id', $categoryId)->count())->toBe(0);
        });
    });
});
