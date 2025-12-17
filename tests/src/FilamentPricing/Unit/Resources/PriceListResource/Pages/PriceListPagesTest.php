<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Resources\PriceListResource;
use AIArmada\FilamentPricing\Resources\PriceListResource\Pages\CreatePriceList;
use AIArmada\FilamentPricing\Resources\PriceListResource\Pages\EditPriceList;
use AIArmada\FilamentPricing\Resources\PriceListResource\Pages\ListPriceLists;
use AIArmada\FilamentPricing\Resources\PriceListResource\Pages\ViewPriceList;

uses(TestCase::class);

it('wires price list pages to the correct resource', function (): void {
    expect(CreatePriceList::getResource())->toBe(PriceListResource::class);
    expect(ListPriceLists::getResource())->toBe(PriceListResource::class);
    expect(ViewPriceList::getResource())->toBe(PriceListResource::class);
    expect(EditPriceList::getResource())->toBe(PriceListResource::class);
});

it('defines header actions for list/view/edit price list pages', function (): void {
    $list = new ListPriceLists();
    $view = new ViewPriceList();
    $edit = new EditPriceList();

    $getHeaderActions = static function (object $page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);

        /** @var array $actions */
        $actions = $method->invoke($page);

        return $actions;
    };

    expect($getHeaderActions($list))->not()->toBeEmpty();
    expect($getHeaderActions($view))->not()->toBeEmpty();
    expect($getHeaderActions($edit))->not()->toBeEmpty();
});
