<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Webhooks\ProcessChipWebhook;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;

describe('ProcessChipWebhook class structure', function (): void {
    it('extends CommerceWebhookProcessor', function (): void {
        $reflection = new ReflectionClass(ProcessChipWebhook::class);
        expect($reflection->isSubclassOf(CommerceWebhookProcessor::class))->toBeTrue();
    });

    it('has processEvent method', function (): void {
        expect(method_exists(ProcessChipWebhook::class, 'processEvent'))->toBeTrue();
    });

    it('has extractPurchase method', function (): void {
        expect(method_exists(ProcessChipWebhook::class, 'extractPurchase'))->toBeTrue();
    });

    it('has extractPayout method', function (): void {
        expect(method_exists(ProcessChipWebhook::class, 'extractPayout'))->toBeTrue();
    });

    it('has extractBillingTemplateClient method', function (): void {
        expect(method_exists(ProcessChipWebhook::class, 'extractBillingTemplateClient'))->toBeTrue();
    });
});

describe('WebhookEventType enum for ProcessChipWebhook', function (): void {
    it('can parse purchase.created event', function (): void {
        $type = WebhookEventType::fromString('purchase.created');
        expect($type)->toBe(WebhookEventType::PurchaseCreated);
    });

    it('can parse purchase.paid event', function (): void {
        $type = WebhookEventType::fromString('purchase.paid');
        expect($type)->toBe(WebhookEventType::PurchasePaid);
    });

    it('can parse purchase.payment_failure event', function (): void {
        $type = WebhookEventType::fromString('purchase.payment_failure');
        expect($type)->toBe(WebhookEventType::PurchasePaymentFailure);
    });

    it('can parse purchase.cancelled event', function (): void {
        $type = WebhookEventType::fromString('purchase.cancelled');
        expect($type)->toBe(WebhookEventType::PurchaseCancelled);
    });

    it('can parse purchase.hold event', function (): void {
        $type = WebhookEventType::fromString('purchase.hold');
        expect($type)->toBe(WebhookEventType::PurchaseHold);
    });

    it('can parse purchase.captured event', function (): void {
        $type = WebhookEventType::fromString('purchase.captured');
        expect($type)->toBe(WebhookEventType::PurchaseCaptured);
    });

    it('can parse purchase.released event', function (): void {
        $type = WebhookEventType::fromString('purchase.released');
        expect($type)->toBe(WebhookEventType::PurchaseReleased);
    });

    it('can parse purchase.preauthorized event', function (): void {
        $type = WebhookEventType::fromString('purchase.preauthorized');
        expect($type)->toBe(WebhookEventType::PurchasePreauthorized);
    });

    it('can parse pending execute event', function (): void {
        $type = WebhookEventType::fromString('purchase.pending_execute');
        expect($type)->toBe(WebhookEventType::PurchasePendingExecute);
    });

    it('can parse pending charge event', function (): void {
        $type = WebhookEventType::fromString('purchase.pending_charge');
        expect($type)->toBe(WebhookEventType::PurchasePendingCharge);
    });

    it('can parse pending capture event', function (): void {
        $type = WebhookEventType::fromString('purchase.pending_capture');
        expect($type)->toBe(WebhookEventType::PurchasePendingCapture);
    });

    it('can parse pending release event', function (): void {
        $type = WebhookEventType::fromString('purchase.pending_release');
        expect($type)->toBe(WebhookEventType::PurchasePendingRelease);
    });

    it('can parse pending refund event', function (): void {
        $type = WebhookEventType::fromString('purchase.pending_refund');
        expect($type)->toBe(WebhookEventType::PurchasePendingRefund);
    });

    it('can parse pending recurring token delete event', function (): void {
        $type = WebhookEventType::fromString('purchase.pending_recurring_token_delete');
        expect($type)->toBe(WebhookEventType::PurchasePendingRecurringTokenDelete);
    });

    it('can parse recurring token deleted event', function (): void {
        $type = WebhookEventType::fromString('purchase.recurring_token_deleted');
        expect($type)->toBe(WebhookEventType::PurchaseRecurringTokenDeleted);
    });

    it('can parse subscription charge failure event', function (): void {
        $type = WebhookEventType::fromString('purchase.subscription_charge_failure');
        expect($type)->toBe(WebhookEventType::PurchaseSubscriptionChargeFailure);
    });

    it('can parse payment refunded event', function (): void {
        $type = WebhookEventType::fromString('payment.refunded');
        expect($type)->toBe(WebhookEventType::PaymentRefunded);
    });

    it('can parse payout pending event', function (): void {
        $type = WebhookEventType::fromString('payout.pending');
        expect($type)->toBe(WebhookEventType::PayoutPending);
    });

    it('can parse payout success event', function (): void {
        $type = WebhookEventType::fromString('payout.success');
        expect($type)->toBe(WebhookEventType::PayoutSuccess);
    });

    it('can parse payout failed event', function (): void {
        $type = WebhookEventType::fromString('payout.failed');
        expect($type)->toBe(WebhookEventType::PayoutFailed);
    });

    it('can parse billing cancelled event', function (): void {
        $type = WebhookEventType::fromString('billing_template_client.subscription_billing_cancelled');
        expect($type)->toBe(WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled);
    });

    it('returns null for unknown event', function (): void {
        $type = WebhookEventType::fromString('unknown.event');
        expect($type)->toBeNull();
    });
});
