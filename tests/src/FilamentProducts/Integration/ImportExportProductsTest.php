<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Pages\ImportExportProducts;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

function setProductsOwner(Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', true);
});

it('exports owner-scoped products to csv (no cross-tenant leakage)', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    setProductsOwner($ownerA);

    OwnerContext::withOwner($ownerA, static function () use ($ownerA): void {
        Product::query()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'name' => 'OwnerA Product',
            'slug' => 'owner-a-product',
            'sku' => 'A-1',
            'currency' => 'MYR',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerB, static function () use ($ownerB): void {
        Product::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'OwnerB Product',
            'slug' => 'owner-b-product',
            'sku' => 'B-1',
            'currency' => 'MYR',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner(null, static function (): void {
        Product::query()->create([
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Global Product',
            'slug' => 'global-product',
            'sku' => 'G-1',
            'currency' => 'MYR',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
    });

    $page = app(ImportExportProducts::class);

    $method = new ReflectionMethod(ImportExportProducts::class, 'exportProducts');
    $method->setAccessible(true);

    /** @var StreamedResponse $response */
    $response = $method->invoke($page, [
        'fields' => ['name', 'sku'],
        'status_filter' => 'all',
    ]);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('name,sku');
    expect($csv)->toContain('OwnerA Product');
    expect($csv)->toContain('Global Product');
    expect($csv)->not->toContain('OwnerB Product');
});

it('does not update cross-tenant products during import when update_existing is enabled', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    setProductsOwner($ownerA);

    $productB = OwnerContext::withOwner($ownerB, static function () use ($ownerB): Product {
        return Product::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'B Original',
            'slug' => 'b-original',
            'sku' => 'SKU-1',
            'currency' => 'MYR',
            'price' => 1000,
            'status' => ProductStatus::Active,
        ]);
    });

    Storage::disk('local')->put('imports/products.csv', implode("\n", [
        'name,sku,currency,price,status',
        'A Imported,SKU-1,MYR,12.34,active',
    ]));

    $page = app(ImportExportProducts::class);
    $page->importData = [
        'csv_file' => ['imports/products.csv'],
        'update_existing' => true,
        'skip_errors' => true,
    ];

    $page->import();

    $productB->refresh();

    expect($productB->name)->toBe('B Original');

    expect(Product::query()->withoutOwnerScope()->where('sku', 'SKU-1')->count())->toBe(1);
    expect(Storage::disk('local')->exists('imports/products.csv'))->toBeFalse();
});
