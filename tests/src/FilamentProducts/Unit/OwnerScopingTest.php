<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', true);

    OwnerContext::clearOverride();

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('scopes ProductResource query to current owner plus global', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $productA = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A',
        'slug' => 'a',
        'status' => ProductStatus::Active,
        'price' => 1000,
    ]);

    $productB = Product::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'name' => 'B',
        'slug' => 'b',
        'status' => ProductStatus::Active,
        'price' => 1000,
    ]);

    $productGlobal = Product::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'G',
        'slug' => 'g',
        'status' => ProductStatus::Active,
        'price' => 1000,
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $ids = ProductResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)
        ->toContain($productA->id)
        ->toContain($productGlobal->id)
        ->not->toContain($productB->id);
});

it('scopes CategoryResource query to current owner plus global', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $catA = Category::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'CatA',
        'slug' => 'cata',
    ]);

    $catB = Category::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'name' => 'CatB',
        'slug' => 'catb',
    ]);

    $catGlobal = Category::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'CatG',
        'slug' => 'catg',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $ids = CategoryResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)
        ->toContain($catA->id)
        ->toContain($catGlobal->id)
        ->not->toContain($catB->id);
});
