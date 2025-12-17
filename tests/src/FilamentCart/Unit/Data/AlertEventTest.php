<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\AlertEvent;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('AlertEvent', function (): void {
    it('can be created with constructor', function (): void {
        $occurredAt = now();
        $event = new AlertEvent(
            event_type: 'abandonment',
            severity: 'warning',
            title: 'Cart Abandoned',
            message: 'A cart was abandoned.',
            cart_id: 'cart-123',
            session_id: 'session-456',
            data: ['value' => 5000],
            occurred_at: $occurredAt,
        );

        expect($event->event_type)->toBe('abandonment');
        expect($event->severity)->toBe('warning');
        expect($event->title)->toBe('Cart Abandoned');
        expect($event->message)->toBe('A cart was abandoned.');
        expect($event->cart_id)->toBe('cart-123');
        expect($event->session_id)->toBe('session-456');
        expect($event->data)->toBe(['value' => 5000]);
        expect($event->occurred_at)->toBe($occurredAt);
    });

    it('can be created from abandonment with low value', function (): void {
        $event = AlertEvent::fromAbandonment(
            cartId: 'cart-123',
            sessionId: 'session-456',
            cartData: ['value_cents' => 5000], // $50
        );

        expect($event->event_type)->toBe('abandonment');
        expect($event->severity)->toBe('info'); // < $100
        expect($event->title)->toBe('Cart Abandoned');
        expect($event->message)->toContain('$50.00');
        expect($event->cart_id)->toBe('cart-123');
        expect($event->session_id)->toBe('session-456');
    });

    it('can be created from abandonment with high value', function (): void {
        $event = AlertEvent::fromAbandonment(
            cartId: 'cart-123',
            sessionId: 'session-456',
            cartData: ['value_cents' => 15000], // $150
        );

        expect($event->severity)->toBe('warning'); // >= $100
        expect($event->message)->toContain('$150.00');
    });

    it('can be created from fraud signal with low risk', function (): void {
        $event = AlertEvent::fromFraud(
            cartId: 'cart-123',
            sessionId: 'session-456',
            fraudData: ['risk_score' => 0.5],
        );

        expect($event->event_type)->toBe('fraud');
        expect($event->severity)->toBe('warning'); // < 0.8
        expect($event->title)->toBe('Fraud Signal Detected');
        expect($event->message)->toContain('0.5');
    });

    it('can be created from fraud signal with high risk', function (): void {
        $event = AlertEvent::fromFraud(
            cartId: 'cart-123',
            sessionId: 'session-456',
            fraudData: ['risk_score' => 0.9],
        );

        expect($event->severity)->toBe('critical'); // >= 0.8
    });

    it('can be created from high value cart', function (): void {
        $event = AlertEvent::fromHighValue(
            cartId: 'cart-123',
            sessionId: 'session-456',
            cartData: ['value_cents' => 50000], // $500
        );

        expect($event->event_type)->toBe('high_value');
        expect($event->severity)->toBe('info');
        expect($event->title)->toBe('High-Value Cart');
        expect($event->message)->toContain('$500.00');
    });

    it('can be created from recovery opportunity', function (): void {
        $event = AlertEvent::fromRecoveryOpportunity(
            cartId: 'cart-123',
            sessionId: 'session-456',
            cartData: ['last_activity' => '2025-01-14'],
        );

        expect($event->event_type)->toBe('recovery');
        expect($event->severity)->toBe('info');
        expect($event->title)->toBe('Recovery Opportunity');
        expect($event->message)->toContain('recoverable');
    });

    it('can be created as custom event', function (): void {
        $event = AlertEvent::custom(
            eventType: 'custom_action',
            severity: 'critical',
            title: 'Custom Alert',
            message: 'Something custom happened.',
            data: ['custom_key' => 'custom_value'],
            cartId: 'cart-999',
            sessionId: 'session-888',
        );

        expect($event->event_type)->toBe('custom_action');
        expect($event->severity)->toBe('critical');
        expect($event->title)->toBe('Custom Alert');
        expect($event->message)->toBe('Something custom happened.');
        expect($event->data)->toBe(['custom_key' => 'custom_value']);
        expect($event->cart_id)->toBe('cart-999');
        expect($event->session_id)->toBe('session-888');
    });

    it('correctly identifies critical severity', function (): void {
        $criticalEvent = AlertEvent::custom(
            eventType: 'test',
            severity: 'critical',
            title: 'Test',
            message: 'Test',
        );

        $warningEvent = AlertEvent::custom(
            eventType: 'test',
            severity: 'warning',
            title: 'Test',
            message: 'Test',
        );

        expect($criticalEvent->isCritical())->toBeTrue();
        expect($warningEvent->isCritical())->toBeFalse();
    });

    it('correctly identifies warning severity', function (): void {
        $warningEvent = AlertEvent::custom(
            eventType: 'test',
            severity: 'warning',
            title: 'Test',
            message: 'Test',
        );

        $infoEvent = AlertEvent::custom(
            eventType: 'test',
            severity: 'info',
            title: 'Test',
            message: 'Test',
        );

        expect($warningEvent->isWarning())->toBeTrue();
        expect($infoEvent->isWarning())->toBeFalse();
    });
});
