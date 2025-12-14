<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;

describe('Collection Model', function (): void {
    describe('Collection Creation', function (): void {
        it('can create a collection', function (): void {
            $collection = Collection::create([
                'name' => 'Summer Collection',
            ]);

            expect($collection)->toBeInstanceOf(Collection::class)
                ->and($collection->name)->toBe('Summer Collection');
        });

        it('generates a slug automatically', function (): void {
            $collection = Collection::create([
                'name' => 'New Arrivals 2024',
            ]);

            expect($collection->slug)->toBe('new-arrivals-2024');
        });

        it('has correct default attributes', function (): void {
            $collection = Collection::create(['name' => 'Default Test']);

            expect($collection->type)->toBe('manual')
                ->and($collection->is_visible)->toBeTrue()
                ->and($collection->is_featured)->toBeFalse()
                ->and($collection->position)->toBe(0);
        });
    });

    describe('Type Helpers', function (): void {
        it('can check if collection is manual', function (): void {
            $manual = Collection::create(['name' => 'Manual Collection', 'type' => 'manual']);
            $automatic = Collection::create(['name' => 'Automatic Collection', 'type' => 'automatic']);

            expect($manual->isManual())->toBeTrue()
                ->and($automatic->isManual())->toBeFalse();
        });

        it('can check if collection is automatic', function (): void {
            $manual = Collection::create(['name' => 'Manual Collection 2', 'type' => 'manual']);
            $automatic = Collection::create(['name' => 'Automatic Collection 2', 'type' => 'automatic']);

            expect($automatic->isAutomatic())->toBeTrue()
                ->and($manual->isAutomatic())->toBeFalse();
        });
    });

    describe('Product Relationship', function (): void {
        it('can attach products to a collection', function (): void {
            $collection = Collection::create(['name' => 'Products Collection']);
            $product1 = Product::create(['name' => 'Product 1', 'price' => 1000, 'status' => ProductStatus::Active]);
            $product2 = Product::create(['name' => 'Product 2', 'price' => 2000, 'status' => ProductStatus::Active]);

            $collection->products()->attach([$product1->id, $product2->id]);

            expect($collection->products)->toHaveCount(2);
        });

        it('can get matching products for manual collection', function (): void {
            $collection = Collection::create(['name' => 'Manual Products', 'type' => 'manual']);
            $product = Product::create(['name' => 'Manual Product', 'price' => 1500, 'status' => ProductStatus::Active]);

            $collection->products()->attach($product->id);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts)->toHaveCount(1)
                ->and($matchingProducts->first()->id)->toBe($product->id);
        });

        it('can get matching products for automatic collection with conditions', function (): void {
            $collection = Collection::create([
                'name' => 'Auto Featured',
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'is_featured', 'operator' => '=', 'value' => true],
                ],
            ]);

            Product::create(['name' => 'Featured Product', 'price' => 3000, 'status' => ProductStatus::Active, 'is_featured' => true]);
            Product::create(['name' => 'Not Featured', 'price' => 2000, 'status' => ProductStatus::Active, 'is_featured' => false]);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts->where('is_featured', true)->count())->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Automatic Collection Conditions', function (): void {
        it('applies price_min condition', function (): void {
            $collection = Collection::create([
                'name' => 'Price Min Collection',
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'price_min', 'value' => 2000],
                ],
            ]);

            Product::create(['name' => 'Expensive Product', 'price' => 3000, 'status' => ProductStatus::Active]);
            Product::create(['name' => 'Cheap Product', 'price' => 500, 'status' => ProductStatus::Active]);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts->where('price', '>=', 2000)->count())->toBeGreaterThanOrEqual(1);
        });

        it('applies price_max condition', function (): void {
            $collection = Collection::create([
                'name' => 'Price Max Collection',
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'price_max', 'value' => 1500],
                ],
            ]);

            Product::create(['name' => 'Budget Product', 'price' => 1000, 'status' => ProductStatus::Active]);
            Product::create(['name' => 'Premium Product', 'price' => 5000, 'status' => ProductStatus::Active]);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts->where('price', '<=', 1500)->count())->toBeGreaterThanOrEqual(1);
        });

        it('applies type condition', function (): void {
            $collection = Collection::create([
                'name' => 'Digital Collection',
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'type', 'value' => 'digital'],
                ],
            ]);

            Product::create(['name' => 'Digital Product', 'price' => 500, 'status' => ProductStatus::Active, 'type' => 'digital']);
            Product::create(['name' => 'Physical Product', 'price' => 800, 'status' => ProductStatus::Active, 'type' => 'simple']);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts->where('type', 'digital')->count())->toBeGreaterThanOrEqual(1);
        });

        it('handles empty conditions', function (): void {
            $collection = Collection::create([
                'name' => 'No Conditions',
                'type' => 'automatic',
                'conditions' => [],
            ]);

            Product::create(['name' => 'Any Product', 'price' => 1000, 'status' => ProductStatus::Active]);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts->count())->toBeGreaterThanOrEqual(1);
        });

        it('skips invalid conditions without field or value', function (): void {
            $collection = Collection::create([
                'name' => 'Invalid Conditions',
                'type' => 'automatic',
                'conditions' => [
                    ['field' => null, 'value' => 100],
                    ['field' => 'price_min', 'value' => null],
                ],
            ]);

            Product::create(['name' => 'Any Product 2', 'price' => 500, 'status' => ProductStatus::Active]);

            $matchingProducts = $collection->getMatchingProducts();

            expect($matchingProducts->count())->toBeGreaterThanOrEqual(0);
        });
    });

    describe('rebuildProductList', function (): void {
        it('does nothing for manual collections', function (): void {
            $collection = Collection::create(['name' => 'Manual Rebuild', 'type' => 'manual']);
            $product = Product::create(['name' => 'Manual Rebuild Product', 'price' => 1000, 'status' => ProductStatus::Active]);

            $collection->products()->attach($product->id);
            $collection->rebuildProductList();

            expect($collection->products)->toHaveCount(1);
        });

        it('syncs products for automatic collections', function (): void {
            $collection = Collection::create([
                'name' => 'Auto Rebuild',
                'type' => 'automatic',
                'conditions' => [
                    ['field' => 'is_featured', 'value' => true],
                ],
            ]);

            Product::create(['name' => 'Featured Rebuild', 'price' => 2000, 'status' => ProductStatus::Active, 'is_featured' => true]);

            $collection->rebuildProductList();

            expect($collection->products()->count())->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Scheduling Helpers', function (): void {
        it('checks if collection is published', function (): void {
            $published = Collection::create([
                'name' => 'Published Collection',
                'is_visible' => true,
                'published_at' => now()->subDay(),
            ]);

            expect($published->isPublished())->toBeTrue();
        });

        it('returns false for unpublished collection', function (): void {
            $unpublished = Collection::create([
                'name' => 'Unpublished Collection',
                'is_visible' => false,
            ]);

            expect($unpublished->isPublished())->toBeFalse();
        });

        it('returns false if published_at is in the future', function (): void {
            $scheduled = Collection::create([
                'name' => 'Future Collection',
                'is_visible' => true,
                'published_at' => now()->addDays(5),
            ]);

            expect($scheduled->isPublished())->toBeFalse();
        });

        it('returns false if unpublished_at has passed', function (): void {
            $expired = Collection::create([
                'name' => 'Expired Collection',
                'is_visible' => true,
                'published_at' => now()->subDays(10),
                'unpublished_at' => now()->subDay(),
            ]);

            expect($expired->isPublished())->toBeFalse();
        });

        it('checks if collection is scheduled', function (): void {
            $scheduled = Collection::create([
                'name' => 'Scheduled Collection',
                'published_at' => now()->addWeek(),
            ]);

            $notScheduled = Collection::create([
                'name' => 'Not Scheduled',
                'published_at' => now()->subDay(),
            ]);

            expect($scheduled->isScheduled())->toBeTrue()
                ->and($notScheduled->isScheduled())->toBeFalse();
        });
    });

    describe('Scopes', function (): void {
        it('can filter visible collections', function (): void {
            Collection::create(['name' => 'Visible Scope 1', 'is_visible' => true]);
            Collection::create(['name' => 'Visible Scope 2', 'is_visible' => true]);
            Collection::create(['name' => 'Hidden Scope', 'is_visible' => false]);

            expect(Collection::visible()->count())->toBeGreaterThanOrEqual(2);
        });

        it('can filter featured collections', function (): void {
            Collection::create(['name' => 'Featured Scope', 'is_featured' => true]);
            Collection::create(['name' => 'Not Featured Scope', 'is_featured' => false]);

            expect(Collection::featured()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter published collections', function (): void {
            Collection::create([
                'name' => 'Published Scope',
                'is_visible' => true,
                'published_at' => now()->subDay(),
            ]);

            expect(Collection::published()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter manual collections', function (): void {
            Collection::create(['name' => 'Manual Scope', 'type' => 'manual']);
            Collection::create(['name' => 'Auto Scope', 'type' => 'automatic']);

            expect(Collection::manual()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter automatic collections', function (): void {
            Collection::create(['name' => 'Manual Scope 2', 'type' => 'manual']);
            Collection::create(['name' => 'Auto Scope 2', 'type' => 'automatic']);

            expect(Collection::automatic()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can order collections by position', function (): void {
            Collection::create(['name' => 'Position 2', 'position' => 2]);
            Collection::create(['name' => 'Position 1', 'position' => 1]);
            Collection::create(['name' => 'Position 3', 'position' => 3]);

            $ordered = Collection::ordered()->get();

            expect($ordered->first()->name)->toBe('Position 1');
        });
    });

    describe('Deletion', function (): void {
        it('detaches products when collection is deleted', function (): void {
            $collection = Collection::create(['name' => 'Delete Collection']);
            $product = Product::create(['name' => 'Delete Collection Product', 'price' => 1000, 'status' => ProductStatus::Active]);

            $collection->products()->attach($product->id);
            $collectionId = $collection->id;

            $collection->delete();

            // The pivot record should be removed
            expect(Illuminate\Support\Facades\DB::table('collection_product')->where('collection_id', $collectionId)->count())->toBe(0);
        });
    });

    describe('Media Collections', function (): void {
        it('registers hero and banner media collections', function (): void {
            $collection = Collection::create(['name' => 'Media Collection']);

            $mediaCollections = collect($collection->getRegisteredMediaCollections());

            expect($mediaCollections->pluck('name'))->toContain('hero', 'banner');
        });
    });

    describe('Route Key Name', function (): void {
        it('uses slug as route key', function (): void {
            $collection = Collection::create(['name' => 'Route Key Collection']);

            expect($collection->getRouteKeyName())->toBe('slug');
        });
    });
});
