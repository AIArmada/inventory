<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Pages\BulkEditProducts;
use AIArmada\FilamentProducts\Pages\ImportExportProducts;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages\ListAttributeGroups;
use AIArmada\FilamentProducts\Resources\AttributeResource\Pages\ListAttributes;
use AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages\ListAttributeSets;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\ListCategories;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\ViewCategory;
use AIArmada\FilamentProducts\Resources\CollectionResource\Pages\ListCollections;
use AIArmada\FilamentProducts\Resources\CollectionResource\Pages\ViewCollection;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\ListProducts;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\ViewProduct;
use AIArmada\FilamentProducts\Widgets\CategoryDistributionChart;
use AIArmada\FilamentProducts\Widgets\LowStockAlertWidget;
use AIArmada\FilamentProducts\Widgets\ProductStatsWidget;
use AIArmada\FilamentProducts\Widgets\TopSellingProductsWidget;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as SchemaFacade;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

function makeProductsTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

function setOwner(Model $owner): void
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
    SchemaFacade::dropIfExists('test_owners');

    SchemaFacade::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', true);
});

it('covers resource page header actions methods', function (): void {
    $listProducts = new class extends ListProducts
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $viewProduct = new class extends ViewProduct
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $listCategories = new class extends ListCategories
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $viewCategory = new class extends ViewCategory
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $listCollections = new class extends ListCollections
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $viewCollection = new class extends ViewCollection
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $listAttributes = new class extends ListAttributes
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $listAttributeGroups = new class extends ListAttributeGroups
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $listAttributeSets = new class extends ListAttributeSets
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    expect($listProducts->headerActions())->toBeArray()->not->toBeEmpty();
    expect($viewProduct->headerActions())->toBeArray()->not->toBeEmpty();
    expect($listCategories->headerActions())->toBeArray()->not->toBeEmpty();
    expect($viewCategory->headerActions())->toBeArray()->not->toBeEmpty();
    expect($listCollections->headerActions())->toBeArray()->not->toBeEmpty();
    expect($viewCollection->headerActions())->toBeArray()->not->toBeEmpty();
    expect($listAttributes->headerActions())->toBeArray()->not->toBeEmpty();
    expect($listAttributeGroups->headerActions())->toBeArray()->not->toBeEmpty();
    expect($listAttributeSets->headerActions())->toBeArray()->not->toBeEmpty();
});

it('covers widget query logic with owner scoping', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    setOwner($ownerA);

    $productA1 = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A1',
        'slug' => 'a1',
        'status' => ProductStatus::Active,
        'type' => ProductType::Simple,
        'price' => 1000,
        'requires_shipping' => true,
    ]);

    $productA2 = Product::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'A2',
        'slug' => 'a2',
        'status' => ProductStatus::Draft,
        'type' => ProductType::Digital,
        'price' => 1000,
        'requires_shipping' => false,
    ]);

    OwnerContext::withOwner($ownerB, static function () use ($ownerB): void {
        Product::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'B1',
            'slug' => 'b1',
            'status' => ProductStatus::Active,
            'type' => ProductType::Simple,
            'price' => 1000,
            'requires_shipping' => true,
        ]);
    });

    $catA = Category::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'name' => 'CatA',
        'slug' => 'cata',
    ]);

    $catB = OwnerContext::withOwner($ownerB, static function () use ($ownerB): Category {
        return Category::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'name' => 'CatB',
            'slug' => 'catb',
        ]);
    });

    $productA1->categories()->attach($catA);
    $productA2->categories()->attach($catA);

    $statsWidget = app(ProductStatsWidget::class);
    $statsMethod = new ReflectionMethod(ProductStatsWidget::class, 'getStats');
    $statsMethod->setAccessible(true);
    $stats = $statsMethod->invoke($statsWidget);

    expect($stats)->toHaveCount(4);
    expect($stats[0]->getValue())->toBe('2');

    $lowStockWidget = app(LowStockAlertWidget::class);
    $lowStatsMethod = new ReflectionMethod(LowStockAlertWidget::class, 'getStats');
    $lowStatsMethod->setAccessible(true);
    $lowStats = $lowStatsMethod->invoke($lowStockWidget);

    expect($lowStats)->toHaveCount(4);

    $chartWidget = app(CategoryDistributionChart::class);
    $dataMethod = new ReflectionMethod(CategoryDistributionChart::class, 'getData');
    $dataMethod->setAccessible(true);
    $data = $dataMethod->invoke($chartWidget);

    expect($data['labels'])->toBe(['CatA']);
    expect($data['datasets'][0]['data'])->toBe([2]);

    $topWidget = app(TopSellingProductsWidget::class);
    $table = $topWidget->table(makeProductsTable());

    expect($table)->toBeInstanceOf(Table::class);
    expect($catB->getKey())->not->toBeNull();
});

it('covers basic table building on standalone pages', function (): void {
    $page = app(BulkEditProducts::class);
    expect($page->table(makeProductsTable()))->toBeInstanceOf(Table::class);

    $importPage = app(ImportExportProducts::class);
    expect($importPage->getImportFormProperty())->toBeInstanceOf(Schema::class);
});
