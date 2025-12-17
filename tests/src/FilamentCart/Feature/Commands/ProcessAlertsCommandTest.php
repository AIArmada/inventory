<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Commands\ProcessAlertsCommand;
use AIArmada\FilamentCart\Models\AlertLog;
use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use AIArmada\FilamentCart\Services\AlertEvaluator;

describe('ProcessAlertsCommand', function (): void {
    it('runs successfully with no rules', function (): void {
        $evaluator = Mockery::mock(AlertEvaluator::class);
        $dispatcher = Mockery::mock(AlertDispatcher::class);

        $this->app->instance(AlertEvaluator::class, $evaluator);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->artisan('cart:process-alerts')
            ->expectsOutput('No active alert rules found.')
            ->assertSuccessful();
    });

    it('processes rules and dispatches alerts', function (): void {
        $rule = AlertRule::create([
            'name' => 'High Value Abandonment',
            'event_type' => 'abandonment',
            'is_active' => true,
            'severity' => 'warning',
            'priority' => 10,
            'conditions' => [],
        ]);

        $evaluator = Mockery::mock(AlertEvaluator::class);
        $evaluator->shouldReceive('evaluate')->once()->andReturn(true);

        $dispatcher = Mockery::mock(AlertDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andReturn(new AlertLog([
            'channels_notified' => ['database']
        ]));

        $this->app->instance(AlertEvaluator::class, $evaluator);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->artisan('cart:process-alerts')
            ->expectsOutputToContain('Processing 1 alert rule(s)...')
            ->expectsOutputToContain('Rule: High Value Abandonment (abandonment)')
            ->expectsOutputToContain('✓ Conditions matched')
            ->assertSuccessful();
    });

    it('skips rules in cooldown', function (): void {
        $rule = AlertRule::create([
            'name' => 'Frequent Alert',
            'event_type' => 'fraud',
            'is_active' => true,
            'cooldown_minutes' => 60,
            'conditions' => [],
        ]);

        // Simulate last triggered just now
        $rule->last_triggered_at = now()->subMinutes(5);
        $rule->save();

        $evaluator = Mockery::mock(AlertEvaluator::class);
        $dispatcher = Mockery::mock(AlertDispatcher::class);

        $this->app->instance(AlertEvaluator::class, $evaluator);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->artisan('cart:process-alerts')
            ->expectsOutputToContain('In cooldown')
            ->assertSuccessful();
    });

    it('supports dry run mode', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'abandonment',
            'is_active' => true,
            'conditions' => [],
        ]);

        $evaluator = Mockery::mock(AlertEvaluator::class);
        $evaluator->shouldReceive('evaluate')->once()->andReturn(true);

        $dispatcher = Mockery::mock(AlertDispatcher::class);
        // Dispatch should NOT be called in dry run

        $this->app->instance(AlertEvaluator::class, $evaluator);
        $this->app->instance(AlertDispatcher::class, $dispatcher);

        $this->artisan('cart:process-alerts', ['--dry-run' => true])
            ->expectsOutput('DRY RUN MODE - No alerts will be dispatched')
            ->expectsOutputToContain('Would dispatch to:')
            ->assertSuccessful();
    });
});
