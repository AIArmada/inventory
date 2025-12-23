<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Policies\CategoryPolicy;
use AIArmada\Products\Policies\ProductPolicy;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Gate;

describe('Policy Registration', function (): void {
    it('registers policies with Gate', function (): void {
        expect(Gate::getPolicyFor(Product::class))->toBeInstanceOf(ProductPolicy::class)
            ->and(Gate::getPolicyFor(Category::class))->toBeInstanceOf(CategoryPolicy::class);
    });
});

describe('Category Policy', function (): void {
    describe('viewAny', function (): void {
        it('allows any user to view all categories', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a@example.com',
                'password' => 'secret',
            ]);

            expect($policy->viewAny($ownerA))->toBeTrue();
        });
    });

    describe('view', function (): void {
        it('allows viewing category for current owner', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-2@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerA, fn () => Category::create(['name' => 'Owned Category']));

            expect($policy->view($ownerA, $category))->toBeTrue();
        });

        it('denies viewing category for a different owner', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-3@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'owner-b@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerB, fn () => Category::create(['name' => 'Other Owner Category']));

            expect($policy->view($ownerA, $category))->toBeFalse();
        });

        it('denies viewing global category when include_global is false', function (): void {
            config()->set('products.features.owner.include_global', false);

            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-4@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner(null, fn () => Category::create(['name' => 'Global Category']));

            expect($policy->view($ownerA, $category))->toBeFalse();
        });

        it('allows viewing global category when include_global is true', function (): void {
            config()->set('products.features.owner.include_global', true);

            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-5@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner(null, fn () => Category::create(['name' => 'Global Category 2']));

            expect($policy->view($ownerA, $category))->toBeTrue();
        });
    });

    describe('create', function (): void {
        it('allows any user to create categories', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-6@example.com',
                'password' => 'secret',
            ]);

            expect($policy->create($ownerA))->toBeTrue();
        });
    });

    describe('update', function (): void {
        it('allows updating category for current owner', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-7@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerA, fn () => Category::create(['name' => 'Update Owned Category']));

            expect($policy->update($ownerA, $category))->toBeTrue();
        });

        it('denies updating category for a different owner', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-8@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'owner-b-2@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerB, fn () => Category::create(['name' => 'Update Other Owner Category']));

            expect($policy->update($ownerA, $category))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting a category without products for current owner', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-9@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerA, fn () => Category::create(['name' => 'Delete Owned Category']));

            expect($policy->delete($ownerA, $category))->toBeTrue();
        });

        it('prevents deleting a category with products', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-10@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerA, fn () => Category::create(['name' => 'Category With Products']));
            $product = OwnerContext::withOwner($ownerA, fn () => Product::create([
                'name' => 'Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]));
            $category->products()->attach($product->id);

            expect($policy->delete($ownerA, $category))->toBeFalse();
        });

        it('denies deleting a category for a different owner', function (): void {
            $policy = new CategoryPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'owner-a-11@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'owner-b-3@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $category = OwnerContext::withOwner($ownerB, fn () => Category::create(['name' => 'Delete Other Owner Category']));

            expect($policy->delete($ownerA, $category))->toBeFalse();
        });
    });
});

describe('Product Policy', function (): void {
    describe('viewAny', function (): void {
        it('allows any user to view all products', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a@example.com',
                'password' => 'secret',
            ]);

            expect($policy->viewAny($ownerA))->toBeTrue();
        });
    });

    describe('view', function (): void {
        it('allows viewing product for current owner', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-2@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerA, fn () => Product::create([
                'name' => 'Owned View Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->view($ownerA, $product))->toBeTrue();
        });

        it('denies viewing product for a different owner', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-3@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'product-owner-b@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerB, fn () => Product::create([
                'name' => 'Other Owner Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->view($ownerA, $product))->toBeFalse();
        });

        it('denies viewing global product when include_global is false', function (): void {
            config()->set('products.features.owner.include_global', false);

            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-4@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner(null, fn () => Product::create([
                'name' => 'Global Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->view($ownerA, $product))->toBeFalse();
        });

        it('allows viewing global product when include_global is true', function (): void {
            config()->set('products.features.owner.include_global', true);

            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-5@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner(null, fn () => Product::create([
                'name' => 'Global Product 2',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->view($ownerA, $product))->toBeTrue();
        });
    });

    describe('create', function (): void {
        it('allows any user to create products', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-6@example.com',
                'password' => 'secret',
            ]);

            expect($policy->create($ownerA))->toBeTrue();
        });
    });

    describe('update', function (): void {
        it('allows updating product for current owner', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-7@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerA, fn () => Product::create([
                'name' => 'Update Owned Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->update($ownerA, $product))->toBeTrue();
        });

        it('denies updating product for a different owner', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-8@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'product-owner-b-2@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerB, fn () => Product::create([
                'name' => 'Update Other Owner Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->update($ownerA, $product))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting product for current owner', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-9@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerA, fn () => Product::create([
                'name' => 'Delete Owned Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->delete($ownerA, $product))->toBeTrue();
        });

        it('denies deleting product for a different owner', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-10@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Owner B',
                'email' => 'product-owner-b-3@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerB, fn () => Product::create([
                'name' => 'Delete Other Owner Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->delete($ownerA, $product))->toBeFalse();
        });
    });

    describe('duplicate', function (): void {
        it('delegates to view policy', function (): void {
            $policy = new ProductPolicy;

            $ownerA = User::query()->create([
                'name' => 'Owner A',
                'email' => 'product-owner-a-11@example.com',
                'password' => 'secret',
            ]);

            OwnerContext::override($ownerA);

            $product = OwnerContext::withOwner($ownerA, fn () => Product::create([
                'name' => 'Duplicate Owned Product',
                'price' => 6000,
                'status' => ProductStatus::Active,
            ]));

            expect($policy->duplicate($ownerA, $product))->toBeTrue();
        });
    });
});
