<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\CheckoutState;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\Expired;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Processing;

describe('CheckoutState', function (): void {
    it('has all expected states', function (): void {
        $session = new CheckoutSession;

        expect(new Pending($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new Processing($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new AwaitingPayment($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new PaymentProcessing($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new PaymentFailed($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new Completed($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new Cancelled($session))->toBeInstanceOf(CheckoutState::class)
            ->and(new Expired($session))->toBeInstanceOf(CheckoutState::class);
    });

    it('states have correct names', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->name())->toBe('pending')
            ->and((new Processing($session))->name())->toBe('processing')
            ->and((new AwaitingPayment($session))->name())->toBe('awaiting_payment')
            ->and((new PaymentProcessing($session))->name())->toBe('payment_processing')
            ->and((new PaymentFailed($session))->name())->toBe('payment_failed')
            ->and((new Completed($session))->name())->toBe('completed')
            ->and((new Cancelled($session))->name())->toBe('cancelled')
            ->and((new Expired($session))->name())->toBe('expired');
    });

    it('states have labels', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->label())->toContain('pending')
            ->and((new Processing($session))->label())->toContain('processing')
            ->and((new Completed($session))->label())->toContain('completed')
            ->and((new Cancelled($session))->label())->toContain('cancelled');
    });

    it('states have colors', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->color())->toBe('gray')
            ->and((new Processing($session))->color())->toBe('info')
            ->and((new Completed($session))->color())->toBe('success')
            ->and((new Cancelled($session))->color())->toBe('gray')
            ->and((new PaymentFailed($session))->color())->toBe('danger');
    });

    it('states have icons', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->icon())->toBe('heroicon-o-clock')
            ->and((new Completed($session))->icon())->toBe('heroicon-o-check-circle')
            ->and((new Cancelled($session))->icon())->toBe('heroicon-o-x-circle');
    });
});

describe('State terminal behavior', function (): void {
    it('identifies terminal states', function (): void {
        $session = new CheckoutSession;

        expect((new Completed($session))->isTerminal())->toBeTrue()
            ->and((new Cancelled($session))->isTerminal())->toBeTrue()
            ->and((new Expired($session))->isTerminal())->toBeTrue()
            ->and((new Pending($session))->isTerminal())->toBeFalse()
            ->and((new Processing($session))->isTerminal())->toBeFalse()
            ->and((new PaymentFailed($session))->isTerminal())->toBeFalse();
    });

    it('identifies states that can retry payment', function (): void {
        $session = new CheckoutSession;

        expect((new PaymentFailed($session))->canRetryPayment())->toBeTrue()
            ->and((new Pending($session))->canRetryPayment())->toBeFalse()
            ->and((new Completed($session))->canRetryPayment())->toBeFalse()
            ->and((new Processing($session))->canRetryPayment())->toBeFalse()
            ->and((new AwaitingPayment($session))->canRetryPayment())->toBeFalse();
    });

    it('identifies states that can be cancelled', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->canCancel())->toBeTrue()
            ->and((new Processing($session))->canCancel())->toBeTrue()
            ->and((new AwaitingPayment($session))->canCancel())->toBeTrue()
            ->and((new PaymentFailed($session))->canCancel())->toBeTrue()
            ->and((new Completed($session))->canCancel())->toBeFalse()
            ->and((new Cancelled($session))->canCancel())->toBeFalse()
            ->and((new Expired($session))->canCancel())->toBeFalse();
    });

    it('identifies states that can be modified', function (): void {
        $session = new CheckoutSession;

        expect((new Pending($session))->canModify())->toBeTrue()
            ->and((new PaymentFailed($session))->canModify())->toBeTrue()
            ->and((new Processing($session))->canModify())->toBeTrue()
            ->and((new Completed($session))->canModify())->toBeFalse()
            ->and((new Cancelled($session))->canModify())->toBeFalse()
            ->and((new PaymentProcessing($session))->canModify())->toBeFalse()
            ->and((new AwaitingPayment($session))->canModify())->toBeFalse();
    });
});

describe('State transitions', function (): void {
    it('has valid state configuration', function (): void {
        $config = CheckoutState::config();

        expect($config)->toBeInstanceOf(\Spatie\ModelStates\StateConfig::class);
    });

    it('has pending as default state', function (): void {
        $config = CheckoutState::config();

        expect($config->defaultStateClass)->toBe(Pending::class);
    });

    it('registers all checkout states', function (): void {
        $expectedStates = [
            Pending::class,
            Processing::class,
            AwaitingPayment::class,
            PaymentProcessing::class,
            PaymentFailed::class,
            Completed::class,
            Cancelled::class,
            Expired::class,
        ];

        foreach ($expectedStates as $state) {
            expect(is_subclass_of($state, CheckoutState::class))->toBeTrue("$state should extend CheckoutState");
        }
    });

    it('allows processing to payment failed transition', function (): void {
        $session = new CheckoutSession;
        $session->status = Processing::class;

        expect($session->status->canTransitionTo(PaymentFailed::class))->toBeTrue();
    });

    it('allows processing to completed transition', function (): void {
        $session = new CheckoutSession;
        $session->status = Processing::class;

        expect($session->status->canTransitionTo(Completed::class))->toBeTrue();
    });
});
