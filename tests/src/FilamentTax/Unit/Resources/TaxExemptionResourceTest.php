<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

it('builds tax exemption resource form schema', function (): void {
    $schema = TaxExemptionResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds tax exemption resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = TaxExemptionResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
});

it('defines pages for tax exemptions', function (): void {
    $pages = TaxExemptionResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'view', 'edit']);
});

it('returns a navigation badge when exemptions are expiring soon', function (): void {
    TaxExemption::query()->delete();
    TaxClass::query()->delete();

    $class = TaxClass::create([
        'name' => 'Standard',
        'slug' => 'standard',
        'is_active' => true,
    ]);

    TaxExemption::create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->id,
        'reason' => 'Test',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    expect(TaxExemptionResource::getNavigationBadge())
        ->toBe('1')
        ->and(TaxExemptionResource::getNavigationBadgeColor())->toBe('warning');
});
