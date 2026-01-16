<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! class_exists('AIArmada\Promotions\Models\Promotion')) {
        $this->markTestSkipped('Promotions package is not installed.');
    }
});

use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\Promotions\Models\Promotion;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('builds promotion resource form schema', function (): void {
    $schema = PromotionResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds promotion resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = PromotionResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
});

it('defines pages for promotions', function (): void {
    $pages = PromotionResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
});

it('shows a navigation badge when active promotions exist', function (): void {
    expect(PromotionResource::getNavigationBadge())->toBeNull();

    Promotion::query()->create([
        'name' => 'Active Promo',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);

    expect(PromotionResource::getNavigationBadge())->toBe('1');
});
