<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentJnt\FilamentJntPlugin;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;
use Filament\Panel;

uses(TestCase::class);

it('exposes a stable plugin id', function (): void {
    expect(FilamentJntPlugin::make()->getId())->toBe('filament-jnt');
});

it('registers JNT resources and widgets when all features enabled', function (): void {
    config()->set('filament-jnt.features', [
        'orders' => true,
        'tracking_events' => true,
        'webhook_logs' => true,
        'widgets' => true,
    ]);

    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            JntOrderResource::class,
            JntTrackingEventResource::class,
            JntWebhookLogResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([JntStatsWidget::class])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    FilamentJntPlugin::make()->register($panel);
});

it('can disable individual resources via config', function (): void {
    config()->set('filament-jnt.features', [
        'orders' => true,
        'tracking_events' => false,
        'webhook_logs' => false,
        'widgets' => false,
    ]);

    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([JntOrderResource::class])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    FilamentJntPlugin::make()->register($panel);
});

it('can disable features via fluent methods', function (): void {
    config()->set('filament-jnt.features', [
        'orders' => true,
        'tracking_events' => true,
        'webhook_logs' => true,
        'widgets' => true,
    ]);

    /** @var Panel&Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([JntOrderResource::class])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    FilamentJntPlugin::make()
        ->trackingEvents(false)
        ->webhookLogs(false)
        ->widgets(false)
        ->register($panel);
});
