<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\RatesRelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('builds tax zone resource form schema', function (): void {
    $schema = TaxZoneResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds tax zone resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = TaxZoneResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
});

it('defines relations and pages for tax zones', function (): void {
    expect(TaxZoneResource::getRelations())
        ->toBe([
            RatesRelationManager::class,
        ]);

    expect(TaxZoneResource::getPages())
        ->toHaveKeys(['index', 'create', 'view', 'edit']);
});
