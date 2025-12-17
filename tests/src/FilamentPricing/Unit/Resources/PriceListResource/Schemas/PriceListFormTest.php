<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Resources\PriceListResource\Schemas\PriceListForm;
use Filament\Schemas\Schema;

uses(TestCase::class);

it('configures the price list form schema', function (): void {
    $schema = PriceListForm::configure(Schema::make());

    $componentKeys = collect($schema->getComponents())
        ->flatMap(fn ($component) => [$component::class])
        ->all();

    expect($componentKeys)->toContain('Filament\\Schemas\\Components\\Grid');
});
