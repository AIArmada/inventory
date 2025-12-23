<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

it('builds tax exemption resource form schema', function (): void {
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;

        public function render(): string
        {
            return '';
        }
    };

    $schema = TaxExemptionResource::form(Schema::make($livewire));

    expect($schema->getComponents())->not()->toBeEmpty();

    $flatten = function (array $components) use (&$flatten): array {
        $all = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            $all[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                $all = [...$all, ...$flatten($component->getChildComponents())];
            }
        }

        return $all;
    };

    $fileUpload = collect($flatten($schema->getComponents()))
        ->first(function (object $component): bool {
            return ($component instanceof FileUpload)
                && method_exists($component, 'getName')
                && ($component->getName() === 'document_path');
        });

    expect($fileUpload)
        ->toBeInstanceOf(FileUpload::class)
        ->and($fileUpload->getDiskName())->toBe('local')
        ->and($fileUpload->getVisibility())->toBe('private')
        ->and($fileUpload->isOpenable())->toBeFalse()
        ->and($fileUpload->isDownloadable())->toBeFalse();
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
