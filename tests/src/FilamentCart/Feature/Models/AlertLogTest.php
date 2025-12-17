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

describe('AlertLog', function (): void {
    it('can be created with required attributes', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'cart_abandoned',
            'severity' => 'warning',
            'title' => 'Cart Abandoned',
            'message' => 'A cart worth $50 was abandoned.',
            'event_data' => ['cart_value' => 5000],
            'channels_notified' => ['email', 'database'],
            'cart_id' => 'cart-123',
            'session_id' => 'session-456',
        ]);

        expect($log)->toBeInstanceOf(AlertLog::class);
        expect($log->id)->not->toBeNull();
        expect($log->event_type)->toBe('cart_abandoned');
        expect($log->severity)->toBe('warning');
        expect($log->title)->toBe('Cart Abandoned');
    });

    it('returns table name from config', function (): void {
        $log = new AlertLog();
        $tableName = $log->getTable();

        expect($tableName)->toContain('alert_logs');
    });

    it('belongs to an alert rule', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Test',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        expect($log->alertRule)->toBeInstanceOf(AlertRule::class);
        expect($log->alertRule->id)->toBe($rule->id);
    });

    it('can mark alert as read', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Test',
            'event_data' => [],
            'channels_notified' => [],
            'is_read' => false,
        ]);

        expect($log->is_read)->toBeFalse();

        $log->markAsRead('user-123');
        $log->refresh();

        expect($log->is_read)->toBeTrue();
        expect($log->read_at)->not->toBeNull();
        expect($log->read_by)->toBe('user-123');
    });

    it('can mark alert as unread', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Test',
            'event_data' => [],
            'channels_notified' => [],
            'is_read' => true,
            'read_at' => now(),
            'read_by' => 'user-123',
        ]);

        $log->markAsUnread();
        $log->refresh();

        expect($log->is_read)->toBeFalse();
        expect($log->read_at)->toBeNull();
        expect($log->read_by)->toBeNull();
    });

    it('can record action taken', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $log = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Test',
            'event_data' => [],
            'channels_notified' => [],
            'action_taken' => false,
        ]);

        expect($log->action_taken)->toBeFalse();

        $log->recordAction('recovery_initiated');
        $log->refresh();

        expect($log->action_taken)->toBeTrue();
        expect($log->action_type)->toBe('recovery_initiated');
        expect($log->action_at)->not->toBeNull();
    });

    it('checks if alert is critical', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $criticalLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'fraud',
            'severity' => 'critical',
            'title' => 'Fraud Detected',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        $warningLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'high_value',
            'severity' => 'warning',
            'title' => 'High Value',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        expect($criticalLog->isCritical())->toBeTrue();
        expect($warningLog->isCritical())->toBeFalse();
    });

    it('checks if alert is warning', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $warningLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'high_value',
            'severity' => 'warning',
            'title' => 'High Value',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        $infoLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'info',
            'severity' => 'info',
            'title' => 'Info',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        expect($warningLog->isWarning())->toBeTrue();
        expect($infoLog->isWarning())->toBeFalse();
    });

    it('returns correct severity color', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
        ]);

        $criticalLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'critical',
            'title' => 'Critical',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        $warningLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'warning',
            'title' => 'Warning',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        $infoLog = AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'test',
            'severity' => 'info',
            'title' => 'Info',
            'event_data' => [],
            'channels_notified' => [],
        ]);

        expect($criticalLog->getSeverityColor())->toBe('danger');
        expect($warningLog->getSeverityColor())->toBe('warning');
        expect($infoLog->getSeverityColor())->toBe('info');
    });
});
