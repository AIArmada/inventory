<?php

declare(strict_types=1);

use AIArmada\FilamentChip\FilamentChipPlugin;
use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use Filament\Panel;

it('registers pages, resources, and widgets on the panel', function (): void {
    $plugin = FilamentChipPlugin::make();
    $panel = Panel::make();

    $plugin->register($panel);

    expect($plugin->getId())->toBe('filament-chip');

    expect($panel->getPages())
        ->toContain(AnalyticsDashboardPage::class);

    expect($panel->getResources())
        ->toContain(PurchaseResource::class);

    expect($panel->getWidgets())
        ->toContain(ChipStatsWidget::class);
});

it('is resolved as a singleton via the container', function (): void {
    $a = app(FilamentChipPlugin::class);
    $b = FilamentChipPlugin::make();

    expect($a)->toBe($b);
});
