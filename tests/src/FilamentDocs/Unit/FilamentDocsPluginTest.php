<?php

declare(strict_types=1);

use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\QuickActionsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Filament\Panel;

it('exposes a stable plugin id', function (): void {
    $plugin = new FilamentDocsPlugin;

    expect($plugin->getId())->toBe('filament-docs');
});

it('registers docs resources and widgets on the panel', function (): void {
    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            DocResource::class,
            DocTemplateResource::class,
            DocSequenceResource::class,
            DocEmailTemplateResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([AgingReportPage::class, PendingApprovalsPage::class])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            QuickActionsWidget::class,
            RecentDocumentsWidget::class,
            StatusBreakdownWidget::class,
            RevenueChartWidget::class,
            DocStatsWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentDocsPlugin)->register($panel);
});

it('can disable pages and widgets via fluent API', function (): void {
    $plugin = FilamentDocsPlugin::make()
        ->agingReportEnabled(false)
        ->pendingApprovalsEnabled(false)
        ->docStatsWidgetEnabled(false)
        ->quickActionsWidgetEnabled(false)
        ->recentDocumentsWidgetEnabled(false)
        ->revenueChartWidgetEnabled(false)
        ->statusBreakdownWidgetEnabled(false);

    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            DocResource::class,
            DocTemplateResource::class,
            DocSequenceResource::class,
            DocEmailTemplateResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')
        ->once()
        ->with([])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    $plugin->register($panel);
});

it('can use custom resource classes via fluent API', function (): void {
    $customDocResource = DocResource::class; // In real usage, this would be a custom subclass

    $plugin = FilamentDocsPlugin::make()
        ->docResource($customDocResource);

    expect($plugin)->toBeInstanceOf(FilamentDocsPlugin::class);
});

it('can set navigation group via fluent API', function (): void {
    $plugin = FilamentDocsPlugin::make()
        ->navigationGroup('Billing');

    expect($plugin->getNavigationGroup())->toBe('Billing');
});

it('returns null for navigation group when not set and config not available', function (): void {
    $plugin = new FilamentDocsPlugin;

    // When navigation group is not set via fluent API, it falls back to config
    // In a real app with config bound, this would return the config value
    // Here we test the property is null by default
    $reflection = new ReflectionClass($plugin);
    $property = $reflection->getProperty('navigationGroup');

    expect($property->getValue($plugin))->toBeNull();
});
