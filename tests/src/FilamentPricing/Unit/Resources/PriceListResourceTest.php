<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\Resources\PriceListResource;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('builds price list resource form schema', function (): void {
    $schema = PriceListResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds price list resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = PriceListResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
});

it('defines relations and pages for price lists', function (): void {
    expect(PriceListResource::getRelations())->not()->toBeEmpty();

    $pages = PriceListResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
});
