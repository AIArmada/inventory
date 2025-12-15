<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Widgets\NetworkVisualizationWidget;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Widgets\RealTimeActivityWidget;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateFraudSignal::query()->delete();
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

// AffiliateStatsWidget Tests
it('AffiliateStatsWidget can be instantiated', function (): void {
    $widget = new AffiliateStatsWidget;

    expect($widget)->toBeInstanceOf(AffiliateStatsWidget::class);
});

it('AffiliateStatsWidget returns correct column count', function (): void {
    $widget = new AffiliateStatsWidget;
    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getColumns');

    expect($method->invoke($widget))->toBe(5);
});

// PerformanceOverviewWidget Tests
it('PerformanceOverviewWidget can be instantiated', function (): void {
    $widget = new PerformanceOverviewWidget;

    expect($widget)->toBeInstanceOf(PerformanceOverviewWidget::class);
});

it('PerformanceOverviewWidget has polling interval', function (): void {
    $widget = new PerformanceOverviewWidget;
    $reflection = new ReflectionClass($widget);
    $property = $reflection->getProperty('pollingInterval');

    expect($property->getValue($widget))->toBe('30s');
});

// RealTimeActivityWidget Tests
it('RealTimeActivityWidget can be instantiated', function (): void {
    $widget = new RealTimeActivityWidget;

    expect($widget)->toBeInstanceOf(RealTimeActivityWidget::class);
});

it('RealTimeActivityWidget has fast polling interval', function (): void {
    $widget = new RealTimeActivityWidget;
    $reflection = new ReflectionClass($widget);
    $property = $reflection->getProperty('pollingInterval');

    expect($property->getValue($widget))->toBe('10s');
});

it('RealTimeActivityWidget has full column span', function (): void {
    $widget = new RealTimeActivityWidget;
    $reflection = new ReflectionClass($widget);
    $property = $reflection->getProperty('columnSpan');

    expect($property->getValue($widget))->toBe('full');
});

// FraudAlertWidget Tests
it('FraudAlertWidget can be instantiated', function (): void {
    $widget = new FraudAlertWidget;

    expect($widget)->toBeInstanceOf(FraudAlertWidget::class);
});

it('FraudAlertWidget has polling interval of 30s', function (): void {
    $widget = new FraudAlertWidget;
    $reflection = new ReflectionClass($widget);
    $property = $reflection->getProperty('pollingInterval');

    expect($property->getValue($widget))->toBe('30s');
});

// PayoutQueueWidget Tests
it('PayoutQueueWidget can be instantiated', function (): void {
    $widget = new PayoutQueueWidget;

    expect($widget)->toBeInstanceOf(PayoutQueueWidget::class);
});

it('PayoutQueueWidget has polling interval of 60s', function (): void {
    $widget = new PayoutQueueWidget;
    $reflection = new ReflectionClass($widget);
    $property = $reflection->getProperty('pollingInterval');

    expect($property->getValue($widget))->toBe('60s');
});

it('PayoutQueueWidget table heading includes pending count', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'WIDG-' . Str::uuid(),
        'name' => 'Widget Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-WID-' . Str::uuid(),
        'amount_minor' => 5000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
    ]);

    $widget = new PayoutQueueWidget;
    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getTableHeading');

    $heading = $method->invoke($widget);

    expect($heading)->toContain('Pending Payouts');
});

// NetworkVisualizationWidget Tests
it('NetworkVisualizationWidget can be instantiated', function (): void {
    $widget = new NetworkVisualizationWidget;

    expect($widget)->toBeInstanceOf(NetworkVisualizationWidget::class);
});

it('NetworkVisualizationWidget can mount with affiliate id', function (): void {
    $widget = new NetworkVisualizationWidget;
    $widget->mount('test-affiliate-id');

    expect($widget->affiliateId)->toBe('test-affiliate-id');
});

it('NetworkVisualizationWidget can mount without affiliate id', function (): void {
    $widget = new NetworkVisualizationWidget;
    $widget->mount(null);

    expect($widget->affiliateId)->toBeNull();
});

it('NetworkVisualizationWidget has default depth of 3', function (): void {
    $widget = new NetworkVisualizationWidget;

    expect($widget->depth)->toBe(3);
});

it('NetworkVisualizationWidget returns empty network data for non-existent affiliate', function (): void {
    $widget = new NetworkVisualizationWidget;
    $widget->mount('non-existent-id');

    expect($widget->getNetworkData())->toBeEmpty();
});

it('NetworkVisualizationWidget returns network stats', function (): void {
    Affiliate::create([
        'code' => 'NET-' . Str::uuid(),
        'name' => 'Network Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $widget = new NetworkVisualizationWidget;
    $stats = $widget->getNetworkStats();

    expect($stats)
        ->toBeArray()
        ->toHaveKey('total_affiliates')
        ->toHaveKey('active_affiliates')
        ->toHaveKey('max_depth')
        ->toHaveKey('avg_children');
});

it('NetworkVisualizationWidget returns root affiliates when no affiliate_id', function (): void {
    Affiliate::create([
        'code' => 'ROOT-' . Str::uuid(),
        'name' => 'Root Affiliate',
        'status' => AffiliateStatus::Active,
        'parent_affiliate_id' => null,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $widget = new NetworkVisualizationWidget;
    $widget->mount(null);
    $data = $widget->getNetworkData();

    expect($data)->toBeArray()
        ->and(count($data))->toBeGreaterThanOrEqual(1);
});
