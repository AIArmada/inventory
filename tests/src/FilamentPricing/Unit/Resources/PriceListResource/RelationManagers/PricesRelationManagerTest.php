<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers\PricesRelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

it('builds prices relation manager form schema', function (): void {
    $manager = new PricesRelationManager();

    $schema = $manager->form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds prices relation manager table definition', function (): void {
    $manager = new PricesRelationManager();

    $livewire = Mockery::mock(HasTable::class);

    $table = $manager->table(Table::make($livewire));

    $columnNames = collect($table->getColumns())
        ->map(fn ($column) => $column->getName())
        ->all();

    expect($columnNames)->toContain('priceable_type');
    expect($columnNames)->toContain('amount');
});
