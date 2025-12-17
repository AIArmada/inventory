<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers\TiersRelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

it('builds tiers relation manager form schema', function (): void {
    $manager = new TiersRelationManager();

    $schema = $manager->form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds tiers relation manager table definition', function (): void {
    $manager = new TiersRelationManager();

    $livewire = Mockery::mock(HasTable::class);

    $table = $manager->table(Table::make($livewire));

    $columnNames = collect($table->getColumns())
        ->map(fn ($column) => $column->getName())
        ->all();

    expect($columnNames)->toContain('quantity_range');
    expect($columnNames)->toContain('pricing_type');
});
