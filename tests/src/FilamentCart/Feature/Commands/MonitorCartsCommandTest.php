<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Commands\MonitorCartsCommand;
use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use AIArmada\FilamentCart\Services\AlertEvaluator;
use AIArmada\FilamentCart\Services\CartMonitor;
use Illuminate\Support\Collection;

describe('MonitorCartsCommand', function (): void {
    it('runs single monitoring pass with --once option and no events', function (): void {
        $monitor = Mockery::mock(CartMonitor::class);
        $monitor->shouldReceive('detectAbandonments')->once()->andReturn(new Collection());
        $monitor->shouldReceive('detectFraudSignals')->once()->andReturn(new Collection());
        $monitor->shouldReceive('detectRecoveryOpportunities')->once()->andReturn(new Collection());
        $monitor->shouldReceive('getHighValueCarts')->once()->andReturn(new Collection());

        $evaluator = Mockery::mock(AlertEvaluator::class);
        $dispatcher = Mockery::mock(AlertDispatcher::class);

        $this->app->instance(CartMonitor::class, $monitor);
        $this->app->instance(AlertEvaluator::class, $evaluator);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->artisan('cart:monitor', ['--once' => true])
            ->assertSuccessful();
    });

    it('processes abandonments with matching rules', function (): void {
        $abandonedCart = (object) [
            'id' => 'cart-123',
            'session_id' => 'session-456',
            'value_cents' => 5000,
        ];

        // Create a real alert rule in DB
        $rule = AlertRule::create([
            'name' => 'High Value Abandonment',
            'event_type' => 'abandonment',
            'conditions' => [],
            'severity' => 'warning',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_database' => true,
        ]);

        $monitor = Mockery::mock(CartMonitor::class);
        $monitor->shouldReceive('detectAbandonments')->once()->andReturn(new Collection([$abandonedCart]));
        $monitor->shouldReceive('detectFraudSignals')->once()->andReturn(new Collection());
        $monitor->shouldReceive('detectRecoveryOpportunities')->once()->andReturn(new Collection());
        $monitor->shouldReceive('getHighValueCarts')->once()->andReturn(new Collection());

        $evaluator = Mockery::mock(AlertEvaluator::class);
        $evaluator->shouldReceive('getMatchingRules')->andReturn(new Collection([$rule]));

        $dispatcher = Mockery::mock(AlertDispatcher::class);
        $alertLog = Mockery::mock(\AIArmada\FilamentCart\Models\AlertLog::class);
        $dispatcher->shouldReceive('dispatch')->once()->andReturn($alertLog);

        $this->app->instance(CartMonitor::class, $monitor);
        $this->app->instance(AlertEvaluator::class, $evaluator);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->artisan('cart:monitor', ['--once' => true])
            ->assertSuccessful();
    });
});
