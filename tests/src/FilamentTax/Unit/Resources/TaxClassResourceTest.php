<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Resources\TaxClassResource;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('builds tax class resource form schema', function (): void {
    $schema = TaxClassResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds tax class resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = TaxClassResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
});

it('defines pages for tax classes', function (): void {
    $pages = TaxClassResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});
