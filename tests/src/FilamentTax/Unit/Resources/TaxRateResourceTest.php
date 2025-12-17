<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Resources\TaxRateResource;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('builds tax rate resource form schema', function (): void {
    $schema = TaxRateResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds tax rate resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = TaxRateResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
});

it('defines pages for tax rates', function (): void {
    $pages = TaxRateResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
});
