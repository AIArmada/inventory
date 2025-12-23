<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Support\Fixtures\TestOwner;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', true);
    config()->set('products.features.owner.auto_assign_on_create', true);

    OwnerContext::clearOverride();

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('scopes Product::forOwner() to current owner plus global', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $productA = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A',
        'price' => 1000,
    ]);

    $productB = Product::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'name' => 'B',
        'price' => 1000,
    ]);

    $productGlobal = Product::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'G',
        'price' => 1000,
    ]);

    expect(fn () => Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'name' => 'CORRUPT',
        'price' => 1000,
    ]))->toThrow(InvalidArgumentException::class);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $ids = Product::query()->forOwner()->pluck('id')->all();

    expect($ids)
        ->toContain($productA->id)
        ->toContain($productGlobal->id)
        ->not->toContain($productB->id)
        ;
});

it('returns strict global-only when owner resolver returns null', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    $productA = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A',
        'price' => 1000,
    ]);

    $productGlobal = Product::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'G',
        'price' => 1000,
    ]);

    expect(fn () => Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'name' => 'CORRUPT',
        'price' => 1000,
    ]))->toThrow(InvalidArgumentException::class);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $ids = Product::query()->forOwner()->pluck('id')->all();

    expect($ids)
        ->toContain($productGlobal->id)
        ->not->toContain($productA->id)
        ;
});

it('auto-assigns owner on create when enabled', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $product = Product::query()->create([
        'name' => 'Auto Assigned',
        'price' => 1000,
    ]);

    expect($product->owner_type)->toBe($ownerA->getMorphClass())
        ->and($product->owner_id)->toBe($ownerA->getKey());
});

it('scopes Category->products to the category owner plus global', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $category = Category::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A Category',
    ]);

    $productA = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A',
        'price' => 1000,
    ]);

    $productB = Product::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'name' => 'B',
        'price' => 1000,
    ]);

    $productGlobal = Product::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'G',
        'price' => 1000,
    ]);

    $category->products()->attach([$productA->id, $productB->id, $productGlobal->id]);

    $ids = $category->products()->pluck('products.id')->all();

    expect($ids)
        ->toContain($productA->id)
        ->toContain($productGlobal->id)
        ->not->toContain($productB->id);
});

it('scopes Collection->products to the collection owner plus global', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $collection = Collection::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A Collection',
        'type' => 'manual',
    ]);

    $productA = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A',
        'price' => 1000,
    ]);

    $productB = Product::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'name' => 'B',
        'price' => 1000,
    ]);

    $productGlobal = Product::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'G',
        'price' => 1000,
    ]);

    $collection->products()->attach([$productA->id, $productB->id, $productGlobal->id]);

    $ids = $collection->products()->pluck('products.id')->all();

    expect($ids)
        ->toContain($productA->id)
        ->toContain($productGlobal->id)
        ->not->toContain($productB->id);
});

it('auto-assigns owner on create for other owner-aware product models', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $category = Category::query()->create(['name' => 'Auto Category']);
    $collection = Collection::query()->create(['name' => 'Auto Collection']);
    $attribute = Attribute::query()->create(['code' => 'color', 'name' => 'Color']);

    expect($category->owner_type)->toBe($ownerA->getMorphClass())
        ->and($category->owner_id)->toBe($ownerA->getKey())
        ->and($collection->owner_type)->toBe($ownerA->getMorphClass())
        ->and($collection->owner_id)->toBe($ownerA->getKey())
        ->and($attribute->owner_type)->toBe($ownerA->getMorphClass())
        ->and($attribute->owner_id)->toBe($ownerA->getKey());
});
