<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\Payment\PaymentStatus;

it('defines all payment status transition rules', function (): void {
    // Test that each status has defined transitions
    $statuses = PaymentStatus::cases();

    expect($statuses)->not->toBeEmpty();

    foreach ($statuses as $status) {
        $allowed = $status->getAllowedTransitions();

        expect($allowed)->toBeArray();

        // Terminal states should have no transitions
        if ($status->isTerminal() && ! in_array($status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED], true)) {
            expect($allowed)->toBeEmpty()
                ->and($status->canTransitionTo($status))->toBeFalse();
        }
    }
});

it('enforces valid pending to paid transition', function (): void {
    expect(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::PAID))->toBeTrue()
        ->and(PaymentStatus::PENDING->getAllowedTransitions())->toContain(PaymentStatus::PAID);
});

it('rejects invalid paid to pending transition', function (): void {
    expect(PaymentStatus::PAID->canTransitionTo(PaymentStatus::PENDING))->toBeFalse()
        ->and(PaymentStatus::PAID->getAllowedTransitions())->not->toContain(PaymentStatus::PENDING);
});

it('allows refund from paid status', function (): void {
    expect(PaymentStatus::PAID->canTransitionTo(PaymentStatus::REFUNDED))->toBeTrue()
        ->and(PaymentStatus::PAID->canTransitionTo(PaymentStatus::PARTIALLY_REFUNDED))->toBeTrue();
});

it('prevents transitions from terminal failed status', function (): void {
    expect(PaymentStatus::FAILED->getAllowedTransitions())->toBeEmpty()
        ->and(PaymentStatus::FAILED->canTransitionTo(PaymentStatus::PENDING))->toBeFalse()
        ->and(PaymentStatus::FAILED->canTransitionTo(PaymentStatus::PAID))->toBeFalse();
});

it('prevents transitions from terminal refunded status', function (): void {
    expect(PaymentStatus::REFUNDED->getAllowedTransitions())->toBeEmpty()
        ->and(PaymentStatus::REFUNDED->canTransitionTo(PaymentStatus::PAID))->toBeFalse();
});

it('allows dispute transitions from paid status', function (): void {
    expect(PaymentStatus::PAID->canTransitionTo(PaymentStatus::DISPUTED))->toBeTrue();
});

it('allows resolution transitions from disputed status', function (): void {
    $allowed = PaymentStatus::DISPUTED->getAllowedTransitions();

    expect($allowed)->toContain(PaymentStatus::PAID)
        ->and($allowed)->toContain(PaymentStatus::REFUNDED);
});

it('validates transition with transitionTo method', function (): void {
    $newStatus = PaymentStatus::PENDING->transitionTo(PaymentStatus::PAID);

    expect($newStatus)->toBe(PaymentStatus::PAID);
});

it('throws exception for invalid transition with transitionTo', function (): void {
    PaymentStatus::REFUNDED->transitionTo(PaymentStatus::PAID);
})->throws(InvalidArgumentException::class, 'Cannot transition payment status from');

it('identifies successful statuses', function (): void {
    expect(PaymentStatus::PAID->isSuccessful())->toBeTrue()
        ->and(PaymentStatus::PARTIALLY_REFUNDED->isSuccessful())->toBeTrue()
        ->and(PaymentStatus::PENDING->isSuccessful())->toBeFalse()
        ->and(PaymentStatus::FAILED->isSuccessful())->toBeFalse();
});

it('identifies pending statuses', function (): void {
    expect(PaymentStatus::PENDING->isPending())->toBeTrue()
        ->and(PaymentStatus::PROCESSING->isPending())->toBeTrue()
        ->and(PaymentStatus::REQUIRES_ACTION->isPending())->toBeTrue()
        ->and(PaymentStatus::PAID->isPending())->toBeFalse();
});

it('identifies terminal statuses', function (): void {
    expect(PaymentStatus::PAID->isTerminal())->toBeTrue()
        ->and(PaymentStatus::REFUNDED->isTerminal())->toBeTrue()
        ->and(PaymentStatus::FAILED->isTerminal())->toBeTrue()
        ->and(PaymentStatus::CANCELLED->isTerminal())->toBeTrue()
        ->and(PaymentStatus::EXPIRED->isTerminal())->toBeTrue()
        ->and(PaymentStatus::PENDING->isTerminal())->toBeFalse();
});

it('identifies refundable statuses', function (): void {
    expect(PaymentStatus::PAID->isRefundable())->toBeTrue()
        ->and(PaymentStatus::PARTIALLY_REFUNDED->isRefundable())->toBeTrue()
        ->and(PaymentStatus::PENDING->isRefundable())->toBeFalse()
        ->and(PaymentStatus::REFUNDED->isRefundable())->toBeFalse();
});

