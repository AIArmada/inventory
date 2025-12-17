<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Widgets\CartStatsWidget;

describe('CartStatsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new CartStatsWidget();
        expect($widget)->toBeInstanceOf(CartStatsWidget::class);
    });

    it('returns 4 columns', function (): void {
        $widget = new CartStatsWidget();
        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('getColumns');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBe(4);
    });
});
