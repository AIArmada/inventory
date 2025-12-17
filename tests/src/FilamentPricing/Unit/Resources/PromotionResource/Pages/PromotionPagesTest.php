<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\FilamentPricing\Resources\PromotionResource\Pages\CreatePromotion;
use AIArmada\FilamentPricing\Resources\PromotionResource\Pages\EditPromotion;
use AIArmada\FilamentPricing\Resources\PromotionResource\Pages\ListPromotions;
use AIArmada\FilamentPricing\Resources\PromotionResource\Pages\ViewPromotion;

uses(TestCase::class);

it('wires promotion pages to the correct resource', function (): void {
    expect(CreatePromotion::getResource())->toBe(PromotionResource::class);
    expect(ListPromotions::getResource())->toBe(PromotionResource::class);
    expect(ViewPromotion::getResource())->toBe(PromotionResource::class);
    expect(EditPromotion::getResource())->toBe(PromotionResource::class);
});

it('defines header actions for list/view/edit promotion pages', function (): void {
    $list = new ListPromotions();
    $view = new ViewPromotion();
    $edit = new EditPromotion();

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
