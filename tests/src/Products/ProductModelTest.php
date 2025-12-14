<?php

declare(strict_types=1);

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Akaunting\Money\Money;

describe('Product Model', function (): void {
    describe('Product Creation', function (): void {
        it('can create a product', function (): void {
            $product = Product::create([
                'name' => 'Test Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product)->toBeInstanceOf(Product::class)
                ->and($product->name)->toBe('Test Product')
                ->and($product->price)->toBe(1000);
        });

        it('generates a slug automatically', function (): void {
            $product = Product::create([
                'name' => 'My Amazing Product',
                'price' => 2000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->slug)->toBe('my-amazing-product');
        });

        it('has correct default attributes', function (): void {
            $product = Product::create([
                'name' => 'Default Product',
                'price' => 1000,
            ]);

            expect($product->type)->toBe(ProductType::Simple)
                ->and($product->status)->toBe(ProductStatus::Draft)
                ->and($product->visibility)->toBe(ProductVisibility::CatalogSearch)
                ->and($product->is_featured)->toBeFalse()
                ->and($product->is_taxable)->toBeTrue()
                ->and($product->requires_shipping)->toBeTrue();
        });

        it('uses slug as route key name', function (): void {
            $product = Product::create([
                'name' => 'Route Key Product',
                'price' => 1000,
            ]);

            expect($product->getRouteKeyName())->toBe('slug');
        });
    });

    describe('Product Status', function (): void {
        it('can check if product is active', function (): void {
            $active = Product::create([
                'name' => 'Active Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $draft = Product::create([
                'name' => 'Draft Product',
                'price' => 1000,
                'status' => ProductStatus::Draft,
            ]);

            expect($active->isActive())->toBeTrue()
                ->and($draft->isActive())->toBeFalse();
        });

        it('can check if product is draft', function (): void {
            $draft = Product::create([
                'name' => 'Draft Product',
                'price' => 1000,
                'status' => ProductStatus::Draft,
            ]);

            expect($draft->isDraft())->toBeTrue();
        });

        it('can check if product is visible', function (): void {
            $active = Product::create([
                'name' => 'Visible Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $draft = Product::create([
                'name' => 'Hidden Product',
                'price' => 1000,
                'status' => ProductStatus::Draft,
            ]);

            expect($active->isVisible())->toBeTrue()
                ->and($draft->isVisible())->toBeFalse();
        });

        it('can check if product is purchasable', function (): void {
            $active = Product::create([
                'name' => 'Purchasable Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $archived = Product::create([
                'name' => 'Not Purchasable Product',
                'price' => 1000,
                'status' => ProductStatus::Archived,
            ]);

            expect($active->isPurchasable())->toBeTrue()
                ->and($archived->isPurchasable())->toBeFalse();
        });

        it('can activate a product', function (): void {
            $product = Product::create([
                'name' => 'Activate Me',
                'price' => 1000,
                'status' => ProductStatus::Draft,
            ]);

            $product->activate();

            expect($product->status)->toBe(ProductStatus::Active)
                ->and($product->published_at)->not->toBeNull();
        });

        it('preserves published_at when already set', function (): void {
            $publishedAt = now()->subDays(5);

            $product = Product::create([
                'name' => 'Pre-published',
                'price' => 1000,
                'status' => ProductStatus::Draft,
                'published_at' => $publishedAt,
            ]);

            $product->activate();

            expect($product->published_at->format('Y-m-d'))->toBe($publishedAt->format('Y-m-d'));
        });

        it('can archive a product', function (): void {
            $product = Product::create([
                'name' => 'Archive Me',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $product->archive();

            expect($product->status)->toBe(ProductStatus::Archived);
        });
    });

    describe('Product Types', function (): void {
        it('can be physical product', function (): void {
            $product = Product::create([
                'name' => 'Physical Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Simple,
            ]);

            expect($product->isPhysical())->toBeTrue();
        });

        it('can be digital product', function (): void {
            $product = Product::create([
                'name' => 'Digital Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Digital,
            ]);

            expect($product->isDigital())->toBeTrue();
        });

        it('can be subscription product', function (): void {
            $product = Product::create([
                'name' => 'Subscription Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Subscription,
            ]);

            expect($product->isSubscription())->toBeTrue();
        });

        it('can check hasVariants for configurable product', function (): void {
            $product = Product::create([
                'name' => 'Configurable Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Configurable,
            ]);

            expect($product->hasVariants())->toBeFalse();

            $product->variants()->create([
                'name' => 'Test Variant',
                'sku' => 'VAR-001',
                'price' => 1000,
            ]);

            expect($product->hasVariants())->toBeTrue();
        });

        it('simple product never has variants', function (): void {
            $product = Product::create([
                'name' => 'Simple Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'type' => ProductType::Simple,
            ]);

            expect($product->hasVariants())->toBeFalse();
        });
    });

    describe('Product Pricing', function (): void {
        it('can format price', function (): void {
            $product = Product::create([
                'name' => 'Priced Product',
                'price' => 1050,
                'currency' => 'MYR',
                'status' => ProductStatus::Active,
            ]);

            expect($product->getFormattedPrice())->toContain('1,050');
        });

        it('can format compare price', function (): void {
            $product = Product::create([
                'name' => 'Compare Price Product',
                'price' => 800,
                'compare_price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getFormattedComparePrice())->toContain('1,000');
        });

        it('returns null for formatted compare price when not set', function (): void {
            $product = Product::create([
                'name' => 'No Compare Price',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getFormattedComparePrice())->toBeNull();
        });

        it('can format cost', function (): void {
            $product = Product::create([
                'name' => 'Cost Product',
                'price' => 1000,
                'cost' => 500,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getFormattedCost())->toContain('500');
        });

        it('returns null for formatted cost when not set', function (): void {
            $product = Product::create([
                'name' => 'No Cost Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getFormattedCost())->toBeNull();
        });

        it('can get price as Money object', function (): void {
            $product = Product::create([
                'name' => 'Money Object Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $money = $product->getPriceAsMoney();

            expect($money)->toBeInstanceOf(Money::class);
        });

        it('can check if product has discount', function (): void {
            $discounted = Product::create([
                'name' => 'Discounted Product',
                'price' => 800,
                'compare_price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $noDiscount = Product::create([
                'name' => 'Regular Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($discounted->hasDiscount())->toBeTrue()
                ->and($noDiscount->hasDiscount())->toBeFalse();
        });

        it('can calculate discount percentage', function (): void {
            $product = Product::create([
                'name' => 'Sale Product',
                'price' => 800,
                'compare_price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getDiscountPercentage())->toBe(20.0);
        });

        it('returns null for discount percentage when no discount', function (): void {
            $product = Product::create([
                'name' => 'No Discount Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getDiscountPercentage())->toBeNull();
        });
    });

    describe('Product Profit Margin', function (): void {
        it('calculates profit margin when cost is set', function (): void {
            $product = Product::create([
                'name' => 'Margin Product',
                'price' => 1000,
                'cost' => 600,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getProfitMargin())->toBe(40.0);
        });

        it('returns null when cost is not set', function (): void {
            $product = Product::create([
                'name' => 'No Cost Product',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getProfitMargin())->toBeNull();
        });

        it('returns null when cost is zero', function (): void {
            $product = Product::create([
                'name' => 'Zero Cost Product',
                'price' => 1000,
                'cost' => 0,
                'status' => ProductStatus::Active,
            ]);

            expect($product->getProfitMargin())->toBeNull();
        });
    });

    describe('Product Scopes', function (): void {
        it('can filter active products', function (): void {
            Product::create(['name' => 'Active', 'price' => 1000, 'status' => ProductStatus::Active]);
            Product::create(['name' => 'Draft', 'price' => 1000, 'status' => ProductStatus::Draft]);
            Product::create(['name' => 'Archived', 'price' => 1000, 'status' => ProductStatus::Archived]);

            expect(Product::active()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter featured products', function (): void {
            Product::create(['name' => 'Featured', 'price' => 1000, 'status' => ProductStatus::Active, 'is_featured' => true]);
            Product::create(['name' => 'Not Featured', 'price' => 1000, 'status' => ProductStatus::Active, 'is_featured' => false]);

            expect(Product::featured()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter visible products', function (): void {
            Product::create([
                'name' => 'Visible Catalog',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::Catalog,
            ]);
            Product::create([
                'name' => 'Hidden',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::Hidden,
            ]);

            expect(Product::visible()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter searchable products', function (): void {
            Product::create([
                'name' => 'Searchable',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::Search,
            ]);
            Product::create([
                'name' => 'Not Searchable',
                'price' => 1000,
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::Individual,
            ]);

            expect(Product::searchable()->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter by type', function (): void {
            Product::create(['name' => 'Digital', 'price' => 1000, 'type' => ProductType::Digital]);
            Product::create(['name' => 'Physical', 'price' => 1000, 'type' => ProductType::Simple]);

            expect(Product::ofType(ProductType::Digital)->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter by category', function (): void {
            $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

            $product = Product::create(['name' => 'In Category', 'price' => 1000]);
            $product->categories()->attach($category->id);

            Product::create(['name' => 'No Category', 'price' => 1000]);

            expect(Product::inCategory($category)->count())->toBeGreaterThanOrEqual(1);
        });

        it('can filter by price range', function (): void {
            Product::create(['name' => 'Cheap', 'price' => 500]);
            Product::create(['name' => 'Mid', 'price' => 1000]);
            Product::create(['name' => 'Expensive', 'price' => 5000]);

            expect(Product::priceRange(400, 1500)->count())->toBeGreaterThanOrEqual(2);
        });
    });

    describe('Product Relationships', function (): void {
        it('can have variants', function (): void {
            $product = Product::create([
                'name' => 'Product With Variants',
                'price' => 1000,
                'type' => ProductType::Configurable,
            ]);

            $product->variants()->create(['name' => 'Variant 1', 'sku' => 'VAR-001', 'price' => 1000]);
            $product->variants()->create(['name' => 'Variant 2', 'sku' => 'VAR-002', 'price' => 1200]);

            expect($product->variants)->toHaveCount(2);
        });

        it('can have options', function (): void {
            $product = Product::create([
                'name' => 'Product With Options',
                'price' => 1000,
            ]);

            $product->options()->create(['name' => 'Color', 'position' => 1]);
            $product->options()->create(['name' => 'Size', 'position' => 2]);

            expect($product->options)->toHaveCount(2);
        });

        it('can belong to categories', function (): void {
            $product = Product::create(['name' => 'Categorized Product', 'price' => 1000]);
            $category1 = Category::create(['name' => 'Category 1', 'slug' => 'cat-1']);
            $category2 = Category::create(['name' => 'Category 2', 'slug' => 'cat-2']);

            $product->categories()->attach([$category1->id, $category2->id]);

            expect($product->categories)->toHaveCount(2);
        });

        it('can belong to collections', function (): void {
            $product = Product::create(['name' => 'Collection Product', 'price' => 1000]);
            $collection1 = Collection::create(['name' => 'Collection 1', 'slug' => 'col-1']);
            $collection2 = Collection::create(['name' => 'Collection 2', 'slug' => 'col-2']);

            $product->collections()->attach([$collection1->id, $collection2->id]);

            expect($product->collections)->toHaveCount(2);
        });
    });

    describe('Buyable Interface', function (): void {
        it('can get buyable identifier', function (): void {
            $product = Product::create(['name' => 'Buyable Product', 'price' => 1000]);

            expect($product->getBuyableIdentifier())->toBe($product->id);
        });

        it('can get buyable description', function (): void {
            $product = Product::create(['name' => 'Buyable Description', 'price' => 1000]);

            expect($product->getBuyableDescription())->toBe('Buyable Description');
        });

        it('can get buyable price', function (): void {
            $product = Product::create(['name' => 'Buyable Price', 'price' => 1500]);

            expect($product->getBuyablePrice())->toBe(1500);
        });

        it('can get buyable weight', function (): void {
            $product = Product::create(['name' => 'Weighted', 'price' => 1000, 'weight' => 2.5]);

            expect($product->getBuyableWeight())->toBe(2.5);
        });

        it('is buyable when purchasable', function (): void {
            $product = Product::create([
                'name' => 'Buyable',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            expect($product->isBuyable())->toBeTrue();
        });
    });

    describe('Priceable Interface', function (): void {
        it('can get base price', function (): void {
            $product = Product::create(['name' => 'Base Price', 'price' => 2000]);

            expect($product->getBasePrice())->toBe(2000);
        });

        it('can get calculated price', function (): void {
            $product = Product::create(['name' => 'Calculated', 'price' => 2000]);

            expect($product->getCalculatedPrice())->toBe(2000);
        });

        it('can get compare price', function (): void {
            $product = Product::create(['name' => 'Compare', 'price' => 800, 'compare_price' => 1000]);

            expect($product->getComparePrice())->toBe(1000);
        });

        it('can check if on sale', function (): void {
            $product = Product::create(['name' => 'On Sale', 'price' => 800, 'compare_price' => 1000]);

            expect($product->isOnSale())->toBeTrue();
        });
    });

    describe('Inventoryable Interface', function (): void {
        it('can get inventory sku', function (): void {
            $product = Product::create(['name' => 'SKU Product', 'price' => 1000, 'sku' => 'PROD-001']);

            expect($product->getInventorySku())->toBe('PROD-001');
        });

        it('returns empty string when no sku', function (): void {
            $product = Product::create(['name' => 'No SKU', 'price' => 1000]);

            expect($product->getInventorySku())->toBe('');
        });

        it('returns stock quantity', function (): void {
            $product = Product::create(['name' => 'Stock Product', 'price' => 1000]);

            expect($product->getStockQuantity())->toBe(0);
        });

        it('checks is in stock', function (): void {
            $product = Product::create(['name' => 'In Stock', 'price' => 1000]);

            expect($product->isInStock())->toBeTrue();
        });

        it('checks has stock', function (): void {
            $product = Product::create(['name' => 'Has Stock', 'price' => 1000]);

            expect($product->hasStock(5))->toBeTrue();
        });

        it('checks tracks inventory for physical', function (): void {
            $physical = Product::create(['name' => 'Physical', 'price' => 1000, 'type' => ProductType::Simple]);
            $digital = Product::create(['name' => 'Digital', 'price' => 1000, 'type' => ProductType::Digital]);

            expect($physical->tracksInventory())->toBeTrue()
                ->and($digital->tracksInventory())->toBeFalse();
        });
    });

    describe('Product Soft Deletes', function (): void {
        it('can soft delete a product', function (): void {
            $product = Product::create([
                'name' => 'To Delete',
                'price' => 1000,
                'status' => ProductStatus::Active,
            ]);

            $id = $product->id;
            $product->delete();

            expect(Product::find($id))->toBeNull()
                ->and(Product::withTrashed()->find($id))->not->toBeNull();
        });

        it('deletes related entities when deleted', function (): void {
            $product = Product::create([
                'name' => 'Delete Relations',
                'price' => 1000,
                'type' => ProductType::Configurable,
            ]);

            $product->variants()->create(['name' => 'Delete Variant', 'sku' => 'DEL-VAR', 'price' => 1000]);
            $product->options()->create(['name' => 'Color', 'position' => 1]);

            $category = Category::create(['name' => 'Del Cat', 'slug' => 'del-cat']);
            $product->categories()->attach($category->id);

            $collection = Collection::create(['name' => 'Del Col', 'slug' => 'del-col']);
            $product->collections()->attach($collection->id);

            $productId = $product->id;
            $product->delete();

            expect(AIArmada\Products\Models\Variant::where('product_id', $productId)->count())->toBe(0)
                ->and(AIArmada\Products\Models\Option::where('product_id', $productId)->count())->toBe(0);
        });
    });
});
