<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;

describe('WebhookEventType enum', function (): void {
    it('has correct purchase lifecycle values', function (): void {
        expect(WebhookEventType::PurchaseCreated->value)->toBe('purchase.created');
        expect(WebhookEventType::PurchasePaid->value)->toBe('purchase.paid');
        expect(WebhookEventType::PurchasePaymentFailure->value)->toBe('purchase.payment_failure');
        expect(WebhookEventType::PurchaseCancelled->value)->toBe('purchase.cancelled');
    });

    it('has correct payout values', function (): void {
        expect(WebhookEventType::PayoutPending->value)->toBe('payout.pending');
        expect(WebhookEventType::PayoutFailed->value)->toBe('payout.failed');
        expect(WebhookEventType::PayoutSuccess->value)->toBe('payout.success');
    });

    it('can create from string', function (): void {
        expect(WebhookEventType::fromString('purchase.paid'))->toBe(WebhookEventType::PurchasePaid);
        expect(WebhookEventType::fromString('payout.success'))->toBe(WebhookEventType::PayoutSuccess);
        expect(WebhookEventType::fromString('unknown.event'))->toBeNull();
    });

    it('returns correct labels', function (): void {
        expect(WebhookEventType::PurchaseCreated->label())->toBe('Purchase Created');
        expect(WebhookEventType::PurchasePaid->label())->toBe('Purchase Paid');
        expect(WebhookEventType::PaymentRefunded->label())->toBe('Payment Refunded');
        expect(WebhookEventType::PayoutSuccess->label())->toBe('Payout Successful');
    });

    it('correctly identifies purchase events', function (): void {
        expect(WebhookEventType::PurchasePaid->isPurchaseEvent())->toBeTrue();
        expect(WebhookEventType::PurchaseCancelled->isPurchaseEvent())->toBeTrue();
        expect(WebhookEventType::PayoutSuccess->isPurchaseEvent())->toBeFalse();
    });

    it('correctly identifies payout events', function (): void {
        expect(WebhookEventType::PayoutSuccess->isPayoutEvent())->toBeTrue();
        expect(WebhookEventType::PayoutFailed->isPayoutEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isPayoutEvent())->toBeFalse();
    });

    it('correctly identifies billing events', function (): void {
        expect(WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled->isBillingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isBillingEvent())->toBeFalse();
    });

    it('correctly identifies payment events', function (): void {
        expect(WebhookEventType::PaymentRefunded->isPaymentEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isPaymentEvent())->toBeFalse();
    });

    it('correctly identifies pending events', function (): void {
        expect(WebhookEventType::PurchasePendingExecute->isPendingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePendingCharge->isPendingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePendingCapture->isPendingEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isPendingEvent())->toBeFalse();
    });

    it('correctly identifies success events', function (): void {
        expect(WebhookEventType::PurchasePaid->isSuccessEvent())->toBeTrue();
        expect(WebhookEventType::PurchaseCaptured->isSuccessEvent())->toBeTrue();
        expect(WebhookEventType::PayoutSuccess->isSuccessEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaymentFailure->isSuccessEvent())->toBeFalse();
    });

    it('correctly identifies failure events', function (): void {
        expect(WebhookEventType::PurchasePaymentFailure->isFailureEvent())->toBeTrue();
        expect(WebhookEventType::PurchaseSubscriptionChargeFailure->isFailureEvent())->toBeTrue();
        expect(WebhookEventType::PayoutFailed->isFailureEvent())->toBeTrue();
        expect(WebhookEventType::PurchasePaid->isFailureEvent())->toBeFalse();
    });

    it('returns correct event class names', function (): void {
        expect(WebhookEventType::PurchasePaid->eventClass())->toBe('AIArmada\\Chip\\Events\\PurchasePaid');
        expect(WebhookEventType::PayoutSuccess->eventClass())->toBe('AIArmada\\Chip\\Events\\PayoutSuccess');
        expect(WebhookEventType::PaymentRefunded->eventClass())->toBe('AIArmada\\Chip\\Events\\PaymentRefunded');
        expect(WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled->eventClass())->toBe('AIArmada\\Chip\\Events\\BillingCancelled');
    });
});
