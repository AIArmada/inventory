<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\CampaignMetrics;

describe('CampaignMetrics', function (): void {
    it('can be created with constructor', function (): void {
        $metrics = new CampaignMetrics(
            total_targeted: 150,
            total_sent: 100,
            total_opened: 50,
            total_clicked: 25,
            total_recovered: 10,
            recovered_revenue_cents: 500000,
            open_rate: 0.5,
            click_rate: 0.25,
            conversion_rate: 0.1,
            average_recovered_value_cents: 50000,
            roi: 5000.0,
            by_channel: [
                'email' => ['attempts' => 60, 'opens' => 30, 'clicks' => 15, 'conversions' => 6],
            ],
            by_attempt_number: [
                '1' => ['attempts' => 100, 'conversions' => 10, 'rate' => 0.1],
            ],
        );

        expect($metrics->total_targeted)->toBe(150);
        expect($metrics->total_sent)->toBe(100);
        expect($metrics->total_opened)->toBe(50);
        expect($metrics->total_clicked)->toBe(25);
        expect($metrics->total_recovered)->toBe(10);
        expect($metrics->recovered_revenue_cents)->toBe(500000);
        expect($metrics->open_rate)->toBe(0.5);
        expect($metrics->click_rate)->toBe(0.25);
        expect($metrics->conversion_rate)->toBe(0.1);
        expect($metrics->average_recovered_value_cents)->toBe(50000);
    });

    it('can be calculated via factory method', function (): void {
        $metrics = CampaignMetrics::calculate(
            totalTargeted: 150,
            totalSent: 100,
            totalOpened: 50,
            totalClicked: 25,
            totalRecovered: 10,
            recoveredRevenueCents: 500000,
        );

        expect($metrics->total_targeted)->toBe(150);
        expect($metrics->total_sent)->toBe(100);
        expect($metrics->open_rate)->toBe(0.5); // 50/100
        expect($metrics->click_rate)->toBe(0.25); // 25/100
        expect($metrics->conversion_rate)->toBe(0.1); // 10/100
        expect($metrics->average_recovered_value_cents)->toBe(50000); // 500000/10
    });

    it('handles zero sent gracefully', function (): void {
        $metrics = CampaignMetrics::calculate(
            totalTargeted: 0,
            totalSent: 0,
            totalOpened: 0,
            totalClicked: 0,
            totalRecovered: 0,
            recoveredRevenueCents: 0,
        );

        expect($metrics->open_rate)->toBe(0.0);
        expect($metrics->click_rate)->toBe(0.0);
        expect($metrics->conversion_rate)->toBe(0.0);
        expect($metrics->average_recovered_value_cents)->toBe(0);
    });

    it('can be converted to array', function (): void {
        $metrics = CampaignMetrics::calculate(
            totalTargeted: 100,
            totalSent: 80,
            totalOpened: 40,
            totalClicked: 20,
            totalRecovered: 5,
            recoveredRevenueCents: 250000,
        );

        $array = $metrics->toArray();

        expect($array)->toBeArray();
        expect($array['total_targeted'])->toBe(100);
        expect($array['total_sent'])->toBe(80);
    });
});
