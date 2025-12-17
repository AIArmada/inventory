<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\AlertEvent;
use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
    Http::fake();
    Mail::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('AlertDispatcher', function (): void {
    it('dispatches alert and creates log entry', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_database' => true,
        ]);

        $event = AlertEvent::custom(
            eventType: 'test',
            severity: 'info',
            title: 'Test Alert',
            message: 'This is a test alert.',
            data: ['key' => 'value'],
            cartId: 'cart-123',
            sessionId: 'session-456',
        );

        $dispatcher = new AlertDispatcher();
        $log = $dispatcher->dispatch($rule, $event);

        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe('test');
        expect($log->severity)->toBe('info');
        expect($log->title)->toBe('Test Alert');
        expect($log->cart_id)->toBe('cart-123');
        expect($log->channels_notified)->toContain('database');
    });

    it('dispatches email when enabled', function (): void {
        $rule = AlertRule::create([
            'name' => 'Email Alert Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'warning',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_email' => true,
            'email_recipients' => ['admin@example.com'],
        ]);

        $event = AlertEvent::custom(
            eventType: 'test',
            severity: 'warning',
            title: 'Test Email Alert',
            message: 'This is a test alert for email.',
        );

        $dispatcher = new AlertDispatcher();
        $log = $dispatcher->dispatch($rule, $event);

        expect($log->channels_notified)->toContain('email');
        // Mail::raw is used, which doesn't go through Mailable system.
        // We verify the email channel was included in channels_notified instead.
    });

    it('dispatches to slack when enabled', function (): void {
        $rule = AlertRule::create([
            'name' => 'Slack Alert Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'critical',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_slack' => true,
            'slack_webhook_url' => 'https://hooks.slack.com/test',
        ]);

        $event = AlertEvent::custom(
            eventType: 'test',
            severity: 'critical',
            title: 'Slack Alert',
            message: 'Critical alert!',
        );

        $dispatcher = new AlertDispatcher();
        $log = $dispatcher->dispatch($rule, $event);

        expect($log->channels_notified)->toContain('slack');
        Http::assertSentCount(1);
    });

    it('dispatches to webhook when enabled', function (): void {
        $rule = AlertRule::create([
            'name' => 'Webhook Alert Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_webhook' => true,
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $event = AlertEvent::custom(
            eventType: 'test',
            severity: 'info',
            title: 'Webhook Alert',
            message: 'Alert for webhook.',
        );

        $dispatcher = new AlertDispatcher();
        $log = $dispatcher->dispatch($rule, $event);

        expect($log->channels_notified)->toContain('webhook');
        Http::assertSentCount(1);
    });

    it('marks rule as triggered after dispatch', function (): void {
        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 30,
            'notify_database' => true,
        ]);

        expect($rule->last_triggered_at)->toBeNull();

        $event = AlertEvent::custom(
            eventType: 'test',
            severity: 'info',
            title: 'Test',
            message: 'Test',
        );

        $dispatcher = new AlertDispatcher();
        $dispatcher->dispatch($rule, $event);

        expect($rule->fresh()->last_triggered_at)->not->toBeNull();
    });

    it('skips email if no recipients configured', function (): void {
        $rule = AlertRule::create([
            'name' => 'Email Alert Rule',
            'event_type' => 'test',
            'conditions' => [],
            'severity' => 'info',
            'priority' => 0,
            'is_active' => true,
            'cooldown_minutes' => 0,
            'notify_email' => true,
            'email_recipients' => [],
        ]);

        $event = AlertEvent::custom(
            eventType: 'test',
            severity: 'info',
            title: 'Test',
            message: 'Test',
        );

        $dispatcher = new AlertDispatcher();
        $log = $dispatcher->dispatch($rule, $event);

        // No email channel because recipients are empty
        expect($log->channels_notified)->not->toContain('email');
    });

    it('formats email message correctly', function (): void {
        $event = AlertEvent::custom(
            eventType: 'abandonment',
            severity: 'warning',
            title: 'Cart Abandoned',
            message: 'A valuable cart was abandoned.',
            data: ['value' => 10000],
            cartId: 'cart-abc',
            sessionId: 'session-xyz',
        );

        // Access private method via reflection
        $dispatcher = new AlertDispatcher();
        $reflection = new ReflectionClass($dispatcher);
        $method = $reflection->getMethod('formatEmailMessage');
        $method->setAccessible(true);

        $result = $method->invoke($dispatcher, $event);

        expect($result)->toContain('Cart Abandoned');
        expect($result)->toContain('A valuable cart was abandoned.');
        expect($result)->toContain('Severity: Warning');
        expect($result)->toContain('Cart ID: cart-abc');
        expect($result)->toContain('Session ID: session-xyz');
    });
});
