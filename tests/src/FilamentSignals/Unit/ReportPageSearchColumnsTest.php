<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\Pages\AcquisitionReport;
use AIArmada\FilamentSignals\Pages\ContentPerformanceReport;
use AIArmada\FilamentSignals\Pages\JourneyReport;
use AIArmada\FilamentSignals\Pages\LiveActivityReport;
use AIArmada\Signals\Models\SignalEvent;
use Filament\Tables\Table;

uses(FilamentSignalsTestCase::class);

it('searches acquisition campaign by the underlying campaign column', function (): void {
    config()->set('filament-signals.resources.labels.outcomes', 'Registrations');
    config()->set('filament-signals.resources.labels.monetary_value', 'Donations');

    $page = app(AcquisitionReport::class);
    $table = $page->table(Table::make($page));
    $column = $table->getColumn('acquisition_campaign');
    $outcomesColumn = $table->getColumn('conversions');
    $monetaryValueColumn = $table->getColumn('revenue_minor');

    expect($column)->not()->toBeNull()
        ->and($column?->getSearchColumns(new SignalEvent))->toBe(['campaign'])
        ->and($outcomesColumn?->getLabel())->toBe('Registrations')
        ->and($monetaryValueColumn?->getLabel())->toBe('Donations');
});

it('searches content path by the underlying path column', function (): void {
    config()->set('filament-signals.resources.labels.outcomes', 'Registrations');
    config()->set('filament-signals.resources.labels.monetary_value', 'Donations');

    $page = app(ContentPerformanceReport::class);
    $table = $page->table(Table::make($page));
    $column = $table->getColumn('content_path');
    $outcomesColumn = $table->getColumn('conversions');
    $monetaryValueColumn = $table->getColumn('revenue_minor');

    expect($column)->not()->toBeNull()
        ->and($column?->getSearchColumns(new SignalEvent))->toBe(['path'])
        ->and($outcomesColumn?->getLabel())->toBe('Registrations')
        ->and($monetaryValueColumn?->getLabel())->toBe('Donations');
});

it('uses the configured monetary value label on live activity tables', function (): void {
    config()->set('filament-signals.resources.labels.monetary_value', 'Donations');

    $page = app(LiveActivityReport::class);
    $table = $page->table(Table::make($page));
    $column = $table->getColumn('revenue_minor');

    expect($column)->not()->toBeNull()
        ->and($column?->getLabel())->toBe('Donations');
});

it('searches journey breakdowns by the underlying session columns', function (): void {
    $page = app(JourneyReport::class);
    $table = $page->table(Table::make($page));
    $column = $table->getColumn('journey_breakdown_value');

    expect($column)->not()->toBeNull()
        ->and($column?->getSearchColumns(new SignalEvent))->toBe(['entry_path', 'exit_path', 'country', 'device_type', 'browser', 'os', 'utm_source', 'utm_medium', 'utm_campaign']);
});
