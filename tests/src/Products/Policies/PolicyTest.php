<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Policies\CategoryPolicy;
use AIArmada\Products\Policies\ProductPolicy;
use Illuminate\Foundation\Auth\User;

describe('Category Policy', function (): void {
    beforeEach(function (): void {
        $this->policy = new CategoryPolicy();
        $this->user = new User();
    });

    describe('viewAny', function (): void {
        it('allows any user to view all categories', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });
    });

    describe('view', function (): void {
        it('allows viewing a category without ownership check', function (): void {
            $category = Category::create(['name' => 'Test Category']);

            expect($this->policy->view($this->user, $category))->toBeTrue();
        });
    });

    describe('create', function (): void {
        it('allows any user to create categories', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });
    });

    describe('update', function (): void {
        it('allows updating a category without ownership check', function (): void {
            $category = Category::create(['name' => 'Update Test Category']);

            expect($this->policy->update($this->user, $category))->toBeTrue();
        });
    });

    describe('delete', function (): void {
        it('allows deleting a category without products', function (): void {
            $category = Category::create(['name' => 'Delete Test Category']);

            expect($this->policy->delete($this->user, $category))->toBeTrue();
        });

        it('prevents deleting a category with products', function (): void {
            $category = Category::create(['name' => 'Category With Products']);
            $product = Product::create([
                'name' => 'Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);
            $category->products()->attach($product->id);

            expect($this->policy->delete($this->user, $category))->toBeFalse();
        });
    });

    describe('restore', function (): void {
        it('delegates to update policy', function (): void {
            $category = Category::create(['name' => 'Restore Test Category']);

            expect($this->policy->restore($this->user, $category))->toBeTrue();
        });
    });

    describe('forceDelete', function (): void {
        it('delegates to delete policy', function (): void {
            $category = Category::create(['name' => 'Force Delete Category']);

            expect($this->policy->forceDelete($this->user, $category))->toBeTrue();
        });

        it('prevents force deleting a category with products', function (): void {
            $category = Category::create(['name' => 'Force Delete Category With Products']);
            $product = Product::create([
                'name' => 'Test Product for Force Delete',
                'price' => 1500,
                'status' => ProductStatus::Active,
            ]);
            $category->products()->attach($product->id);

            expect($this->policy->forceDelete($this->user, $category))->toBeFalse();
        });
    });
});

describe('Product Policy', function (): void {
    beforeEach(function (): void {
        $this->policy = new ProductPolicy();
        $this->user = new User();
    });

    describe('viewAny', function (): void {
        it('allows any user to view all products', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });
    });

    describe('view', function (): void {
        it('allows viewing a product without ownership check', function (): void {
            $product = Product::create([
                'name' => 'View Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($this->policy->view($this->user, $product))->toBeTrue();
        });
    });

    describe('create', function (): void {
        it('allows any user to create products', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });
    });

    describe('update', function (): void {
        it('allows updating a product without ownership check', function (): void {
            $product = Product::create([
                'name' => 'Update Test Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            expect($this->policy->update($this->user, $product))->toBeTrue();
        });
    });

    describe('delete', function (): void {
        it('allows deleting a product without ownership check', function (): void {
            $product = Product::create([
                'name' => 'Delete Test Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]);

            expect($this->policy->delete($this->user, $product))->toBeTrue();
        });
    });

    describe('restore', function (): void {
        it('delegates to update policy', function (): void {
            $product = Product::create([
                'name' => 'Restore Test Product',
                'price' => 4000,
                'status' => ProductStatus::Active,
            ]);

            expect($this->policy->restore($this->user, $product))->toBeTrue();
        });
    });

    describe('forceDelete', function (): void {
        it('delegates to delete policy', function (): void {
            $product = Product::create([
                'name' => 'Force Delete Test Product',
                'price' => 5000,
                'status' => ProductStatus::Active,
            ]);

            expect($this->policy->forceDelete($this->user, $product))->toBeTrue();
        });
    });

    describe('duplicate', function (): void {
        it('delegates to view policy', function (): void {
            $product = Product::create([
                'name' => 'Duplicate Test Product',
                'price' => 6000,
                'status' => ProductStatus::Active,
            ]);

            expect($this->policy->duplicate($this->user, $product))->toBeTrue();
        });
    });
});
