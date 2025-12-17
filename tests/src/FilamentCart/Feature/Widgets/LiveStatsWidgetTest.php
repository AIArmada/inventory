<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Widgets\LiveStatsWidget;

describe('LiveStatsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new LiveStatsWidget();
        expect($widget)->toBeInstanceOf(LiveStatsWidget::class);
    });

    it('uses 10 second polling interval', function (): void {
        $widget = new LiveStatsWidget();
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('pollingInterval');
        $property->setAccessible(true);

        expect($property->getValue($widget))->toBe('10s');
    });

    it('spans full width', function (): void {
        $widget = new LiveStatsWidget();
        $reflection = new ReflectionClass($widget);
        $property = $reflection->getProperty('columnSpan');
        $property->setAccessible(true);

        expect($property->getValue($widget))->toBe('full');
    });
});
