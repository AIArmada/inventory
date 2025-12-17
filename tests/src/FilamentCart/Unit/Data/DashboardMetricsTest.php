<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\DashboardMetrics;

describe('DashboardMetrics', function (): void {
    it('can be created with constructor', function (): void {
        $metrics = new DashboardMetrics(
            total_carts: 1000,
            active_carts: 200,
            abandoned_carts: 500,
            recovered_carts: 100,
            total_value_cents: 5000000,
            conversion_rate: 0.3,
            abandonment_rate: 0.5,
            recovery_rate: 0.2,
            average_cart_value_cents: 5000,
        );

        expect($metrics->total_carts)->toBe(1000);
        expect($metrics->active_carts)->toBe(200);
        expect($metrics->abandoned_carts)->toBe(500);
        expect($metrics->recovered_carts)->toBe(100);
        expect($metrics->total_value_cents)->toBe(5000000);
        expect($metrics->conversion_rate)->toBe(0.3);
        expect($metrics->abandonment_rate)->toBe(0.5);
        expect($metrics->recovery_rate)->toBe(0.2);
        expect($metrics->average_cart_value_cents)->toBe(5000);
    });

    it('can include optional change rates', function (): void {
        $metrics = new DashboardMetrics(
            total_carts: 1000,
            active_carts: 200,
            abandoned_carts: 500,
            recovered_carts: 100,
            total_value_cents: 5000000,
            conversion_rate: 0.3,
            abandonment_rate: 0.5,
            recovery_rate: 0.2,
            average_cart_value_cents: 5000,
            conversion_rate_change: 0.05,
            abandonment_rate_change: -0.03,
            recovery_rate_change: 0.10,
        );

        expect($metrics->conversion_rate_change)->toBe(0.05);
        expect($metrics->abandonment_rate_change)->toBe(-0.03);
        expect($metrics->recovery_rate_change)->toBe(0.10);
    });

    it('defaults change rates to null', function (): void {
        $metrics = new DashboardMetrics(
            total_carts: 100,
            active_carts: 50,
            abandoned_carts: 25,
            recovered_carts: 10,
            total_value_cents: 100000,
            conversion_rate: 0.25,
            abandonment_rate: 0.25,
            recovery_rate: 0.4,
            average_cart_value_cents: 1000,
        );

        expect($metrics->conversion_rate_change)->toBeNull();
        expect($metrics->abandonment_rate_change)->toBeNull();
        expect($metrics->recovery_rate_change)->toBeNull();
    });

    it('can be converted to array', function (): void {
        $metrics = new DashboardMetrics(
            total_carts: 1000,
            active_carts: 200,
            abandoned_carts: 500,
            recovered_carts: 100,
            total_value_cents: 5000000,
            conversion_rate: 0.3,
            abandonment_rate: 0.5,
            recovery_rate: 0.2,
            average_cart_value_cents: 5000,
        );

        $array = $metrics->toArray();

        expect($array)->toBeArray();
        expect($array['total_carts'])->toBe(1000);
        expect($array['conversion_rate'])->toBe(0.3);
    });
});
