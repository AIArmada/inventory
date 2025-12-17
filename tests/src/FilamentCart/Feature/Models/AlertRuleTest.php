<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\AlertLog;
use AIArmada\FilamentCart\Models\AlertRule;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('AlertRule', function (): void {
    it('can be created with required attributes', function (): void {
        $rule = AlertRule::create([
            'name' => 'High Value Cart Alert',
            'description' => 'Alerts when cart value exceeds threshold',
            'event_type' => 'high_value',
            'conditions' => ['field' => 'cart_value', 'operator' => '>=', 'value' => 10000],
            'notify_database' => true,
            'severity' => 'warning',
            'priority' => 1,
            'is_active' => true,
            'cooldown_minutes' => 30,
        ]);

        expect($rule)->toBeInstanceOf(AlertRule::class);
        expect($rule->id)->not->toBeNull();
        expect($rule->name)->toBe('High Value Cart Alert');
        expect($rule->event_type)->toBe('high_value');
        expect($rule->severity)->toBe('warning');
        expect($rule->is_active)->toBeTrue();
    });

    it('returns table name from config', function (): void {
        $rule = new AlertRule();
        $tableName = $rule->getTable();

        expect($tableName)->toContain('alert_rules');
    });

    it('casts conditions as array', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => ['field' => 'value', 'operator' => '=', 'value' => 100],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $rule->refresh();

        expect($rule->conditions)->toBeArray();
        expect($rule->conditions['field'])->toBe('value');
    });

    it('can check if rule is in cooldown', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 60,
            'last_triggered_at' => null,
        ]);

        // Not triggered yet, not in cooldown
        expect($rule->isInCooldown())->toBeFalse();

        // Trigger it
        $rule->update(['last_triggered_at' => now()]);
        expect($rule->isInCooldown())->toBeTrue();

        // Advance time past cooldown
        Carbon::setTestNow(now()->addMinutes(61));
        expect($rule->fresh()->isInCooldown())->toBeFalse();
    });

    it('can get cooldown remaining minutes', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 60,
            'last_triggered_at' => now(),
        ]);

        $remaining = $rule->getCooldownRemainingMinutes();
        expect($remaining)->toBe(60);

        // Not in cooldown = 0 remaining
        Carbon::setTestNow(now()->addMinutes(61));
        expect($rule->fresh()->getCooldownRemainingMinutes())->toBe(0);
    });

    it('can mark rule as triggered', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 30,
        ]);

        expect($rule->last_triggered_at)->toBeNull();

        $rule->markTriggered();

        expect($rule->fresh()->last_triggered_at)->not->toBeNull();
    });

    it('can get enabled notification channels', function (): void {
        $rule = AlertRule::create([
            'name' => 'Multi-channel Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_email' => true,
            'notify_slack' => true,
            'notify_webhook' => false,
            'notify_database' => true,
        ]);

        $channels = $rule->getEnabledChannels();

        expect($channels)->toContain('email');
        expect($channels)->toContain('slack');
        expect($channels)->toContain('database');
        expect($channels)->not->toContain('webhook');
    });

    it('deletes logs when rule is deleted', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        // Create associated logs
        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Test Alert',
            'message' => 'Test message',
            'event_data' => [],
            'channels_notified' => ['database'],
        ]);

        expect(AlertLog::where('alert_rule_id', $rule->id)->count())->toBe(1);

        // Delete rule
        $rule->delete();

        // Logs should also be deleted (cascade)
        expect(AlertLog::where('alert_rule_id', $rule->id)->count())->toBe(0);
    });
});
