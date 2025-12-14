<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Events\ProductCreated;
use AIArmada\Products\Events\ProductDeleted;
use AIArmada\Products\Events\ProductStatusChanged;
use AIArmada\Products\Events\ProductUpdated;
use AIArmada\Products\Events\VariantsGenerated;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;

describe('Product Events', function (): void {
    describe('ProductCreated Event', function (): void {
        it('can be instantiated with a product', function (): void {
            $product = Product::create([
                'name' => 'Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $event = new ProductCreated($product);

            expect($event->product)->toBe($product);
        });

        it('can be dispatched', function (): void {
            $product = Product::create([
                'name' => 'Event Test Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            $dispatched = false;
            Event::listen(ProductCreated::class, function ($event) use (&$dispatched, $product): void {
                $dispatched = true;
                expect($event->product->id)->toBe($product->id);
            });

            ProductCreated::dispatch($product);

            expect($dispatched)->toBeTrue();
        });
    });

    describe('ProductUpdated Event', function (): void {
        it('can be instantiated with a product', function (): void {
            $product = Product::create([
                'name' => 'Updated Product',
                'price' => 1500,
                'status' => ProductStatus::Active,
            ]);

            $event = new ProductUpdated($product);

            expect($event->product)->toBe($product);
        });

        it('can be dispatched', function (): void {
            $product = Product::create([
                'name' => 'Update Event Product',
                'price' => 2500,
                'status' => ProductStatus::Active,
            ]);

            $dispatched = false;
            Event::listen(ProductUpdated::class, function ($event) use (&$dispatched, $product): void {
                $dispatched = true;
                expect($event->product->id)->toBe($product->id);
            });

            ProductUpdated::dispatch($product);

            expect($dispatched)->toBeTrue();
        });
    });

    describe('ProductDeleted Event', function (): void {
        it('can be instantiated with a product', function (): void {
            $product = Product::create([
                'name' => 'Deleted Product',
                'price' => 1200,
                'status' => ProductStatus::Active,
            ]);

            $event = new ProductDeleted($product);

            expect($event->product)->toBe($product);
        });

        it('can be dispatched', function (): void {
            $product = Product::create([
                'name' => 'Delete Event Product',
                'price' => 3000,
                'status' => ProductStatus::Active,
            ]);

            $dispatched = false;
            Event::listen(ProductDeleted::class, function ($event) use (&$dispatched, $product): void {
                $dispatched = true;
                expect($event->product->id)->toBe($product->id);
            });

            ProductDeleted::dispatch($product);

            expect($dispatched)->toBeTrue();
        });
    });

    describe('ProductStatusChanged Event', function (): void {
        it('can be instantiated with product and status changes', function (): void {
            $product = Product::create([
                'name' => 'Status Changed Product',
                'price' => 1800,
                'status' => ProductStatus::Active,
            ]);

            $event = new ProductStatusChanged($product, ProductStatus::Draft, ProductStatus::Active);

            expect($event->product)->toBe($product)
                ->and($event->oldStatus)->toBe(ProductStatus::Draft)
                ->and($event->newStatus)->toBe(ProductStatus::Active);
        });

        it('can be dispatched', function (): void {
            $product = Product::create([
                'name' => 'Status Event Product',
                'price' => 4000,
                'status' => ProductStatus::Active,
            ]);

            $dispatched = false;
            Event::listen(ProductStatusChanged::class, function ($event) use (&$dispatched): void {
                $dispatched = true;
                expect($event->oldStatus)->toBe(ProductStatus::Draft)
                    ->and($event->newStatus)->toBe(ProductStatus::Active);
            });

            ProductStatusChanged::dispatch($product, ProductStatus::Draft, ProductStatus::Active);

            expect($dispatched)->toBeTrue();
        });
    });

    describe('VariantsGenerated Event', function (): void {
        it('can be instantiated with product and variants', function (): void {
            $product = Product::create([
                'name' => 'Variants Product',
                'price' => 2200,
                'status' => ProductStatus::Active,
            ]);

            $variant1 = Variant::create([
                'product_id' => $product->id,
                'name' => 'Variant 1',
                'sku' => 'VAR-001',
            ]);

            $variant2 = Variant::create([
                'product_id' => $product->id,
                'name' => 'Variant 2',
                'sku' => 'VAR-002',
            ]);

            $variants = collect([$variant1, $variant2]);
            $event = new VariantsGenerated($product, $variants);

            expect($event->product)->toBe($product)
                ->and($event->variants)->toHaveCount(2)
                ->and($event->variants->first()->sku)->toBe('VAR-001');
        });

        it('can be dispatched', function (): void {
            $product = Product::create([
                'name' => 'Variants Event Product',
                'price' => 5000,
                'status' => ProductStatus::Active,
            ]);

            $variant = Variant::create([
                'product_id' => $product->id,
                'name' => 'Event Variant',
                'sku' => 'VAR-EVT-001',
            ]);

            $variants = collect([$variant]);

            $dispatched = false;
            Event::listen(VariantsGenerated::class, function ($event) use (&$dispatched): void {
                $dispatched = true;
                expect($event->variants)->toHaveCount(1);
            });

            VariantsGenerated::dispatch($product, $variants);

            expect($dispatched)->toBeTrue();
        });

        it('works with empty variants collection', function (): void {
            $product = Product::create([
                'name' => 'Empty Variants Product',
                'price' => 1100,
                'status' => ProductStatus::Active,
            ]);

            $event = new VariantsGenerated($product, collect());

            expect($event->variants)->toBeEmpty();
        });
    });
});
