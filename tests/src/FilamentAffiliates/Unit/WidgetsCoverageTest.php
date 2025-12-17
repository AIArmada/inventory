<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Widgets\FraudAlertWidget;
use AIArmada\FilamentAffiliates\Widgets\PayoutQueueWidget;
use AIArmada\FilamentAffiliates\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Widgets\RealTimeActivityWidget;
use Filament\Tables\Table;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

it('AffiliateStatsWidget builds stats', function (): void {
    $this->app->instance(AffiliateStatsAggregator::class, new class
    {
        public function overview(): array
        {
            return [
                'active_affiliates' => 2,
                'total_affiliates' => 5,
                'pending_affiliates' => 1,
                'pending_commission_minor' => 12345,
                'paid_commission_minor' => 67890,
                'conversion_rate' => 12.3,
            ];
        }
    });

    $widget = new AffiliateStatsWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');
    $method->setAccessible(true);

    $stats = $method->invoke($widget);

    expect($stats)->toBeArray()->and(count($stats))->toBe(5);
});

it('PerformanceOverviewWidget builds stats and computes changes', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PERF-' . Str::uuid(),
        'name' => 'Perf Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    // This month
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-MONTH',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => 'approved',
        'occurred_at' => now()->startOfMonth()->addDay(),
    ]);

    // Last month
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-LAST',
        'total_minor' => 5000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => 'approved',
        'occurred_at' => now()->subMonth()->startOfMonth()->addDay(),
    ]);

    $widget = new PerformanceOverviewWidget;

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');
    $method->setAccessible(true);

    $stats = $method->invoke($widget);

    expect($stats)->toBeArray()->and(count($stats))->toBe(4);
});

it('RealTimeActivityWidget configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();

    $widget = new RealTimeActivityWidget;
    $widget->table($table);

    expect(true)->toBeTrue();
});

it('FraudAlertWidget configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();
    $table->shouldReceive('emptyStateHeading')->once()->andReturnSelf();
    $table->shouldReceive('emptyStateDescription')->once()->andReturnSelf();
    $table->shouldReceive('emptyStateIcon')->once()->andReturnSelf();

    $widget = new FraudAlertWidget;
    $widget->table($table);

    expect(true)->toBeTrue();
});

it('PayoutQueueWidget configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();
    $table->shouldReceive('emptyStateHeading')->once()->andReturnSelf();
    $table->shouldReceive('emptyStateDescription')->once()->andReturnSelf();
    $table->shouldReceive('emptyStateIcon')->once()->andReturnSelf();

    $widget = new PayoutQueueWidget;
    $widget->table($table);

    expect(true)->toBeTrue();
});
