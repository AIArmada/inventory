<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\Pages\PriceSimulator;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

function findSectionByHeading(Schema $schema, string $heading): ?Section
{
    foreach ($schema->getComponents(withActions: false, withHidden: true) as $component) {
        if (($component instanceof Section) && ($component->getHeading() === $heading)) {
            return $component;
        }

        if (! $component instanceof \Filament\Schemas\Components\Component) {
            continue;
        }

        foreach ($component->getChildSchemas(withHidden: true) as $childSchema) {
            $found = findSectionByHeading($childSchema, $heading);

            if ($found instanceof Section) {
                return $found;
            }
        }
    }

    return null;
}

it('hides the applied rules section when no pricing rules were applied', function (): void {
    $page = app(PriceSimulator::class);

    $page->result = [
        'original_price' => 1000,
        'final_price' => 1000,
        'discount_amount' => 0,
        'discount_source' => null,
        'discount_percentage' => 0,
        'price_list_name' => null,
        'tier_description' => null,
        'promotion_name' => null,
        'breakdown' => [],
        'quantity' => 1,
        'unit_price' => 1000,
        'total_price' => 1000,
    ];

    $schema = $page->resultInfolist(Schema::make($page));

    $section = findSectionByHeading($schema, 'Applied Pricing Rules');

    expect($section)->not()->toBeNull();
    expect($section?->isVisible())->toBeFalse();
});

it('shows the applied rules section when a pricing rule is present', function (): void {
    $page = app(PriceSimulator::class);

    $page->result = [
        'original_price' => 1000,
        'final_price' => 900,
        'discount_amount' => 100,
        'discount_source' => 'promotion',
        'discount_percentage' => 10,
        'price_list_name' => 'Default',
        'tier_description' => null,
        'promotion_name' => null,
        'breakdown' => [],
        'quantity' => 1,
        'unit_price' => 900,
        'total_price' => 900,
    ];

    $schema = $page->resultInfolist(Schema::make($page));

    $section = findSectionByHeading($schema, 'Applied Pricing Rules');

    expect($section)->not()->toBeNull();
    expect($section?->isVisible())->toBeTrue();
});

it('toggles breakdown section visibility based on breakdown state', function (): void {
    $page = app(PriceSimulator::class);

    $page->result = [
        'original_price' => 1000,
        'final_price' => 900,
        'discount_amount' => 100,
        'discount_source' => 'promotion',
        'discount_percentage' => 10,
        'price_list_name' => 'Default',
        'tier_description' => null,
        'promotion_name' => 'Promo',
        'breakdown' => [],
        'quantity' => 1,
        'unit_price' => 900,
        'total_price' => 900,
    ];

    $schema = $page->resultInfolist(Schema::make($page));
    $breakdown = findSectionByHeading($schema, 'Breakdown');

    expect($breakdown)->not()->toBeNull();
    expect($breakdown?->isVisible())->toBeFalse();

    $page->result['breakdown'] = [
        ['step' => 'Base', 'value' => 1000],
    ];

    $schema = $page->resultInfolist(Schema::make($page));
    $breakdown = findSectionByHeading($schema, 'Breakdown');

    expect($breakdown)->not()->toBeNull();
    expect($breakdown?->isVisible())->toBeTrue();
});

it('toggles clear header action visibility based on result state', function (): void {
    $page = app(PriceSimulator::class);
    $page->result = null;

    $method = new \ReflectionMethod($page, 'getHeaderActions');
    $method->setAccessible(true);

    $actions = $method->invoke($page);
    $clearAction = collect($actions)->first(fn ($action) => method_exists($action, 'getName') && ($action->getName() === 'clear'));

    expect($clearAction)->not()->toBeNull();
    expect($clearAction->isVisible())->toBeFalse();

    $page->result = [];

    $actions = $method->invoke($page);
    $clearAction = collect($actions)->first(fn ($action) => method_exists($action, 'getName') && ($action->getName() === 'clear'));

    expect($clearAction)->not()->toBeNull();
    expect($clearAction->isVisible())->toBeTrue();
});
