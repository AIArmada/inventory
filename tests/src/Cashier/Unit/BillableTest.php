<?php

declare(strict_types=1);

use AIArmada\Cashier\Billable;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Billable Trait', function (): void {
    beforeEach(function (): void {
        $this->user = $this->createUser();
    });

    describe('onGenericTrial', function (): void {
        it('returns false when trial_ends_at is null', function (): void {
            $onTrial = $this->user->onGenericTrial();

            expect($onTrial)->toBeFalse();
        });

        it('returns false when trial has ended', function (): void {
            $this->user->trial_ends_at = now()->subDay();
            $this->user->save();

            $onTrial = $this->user->onGenericTrial();

            expect($onTrial)->toBeFalse();
        });

        it('returns true when trial is active', function (): void {
            $this->user->trial_ends_at = now()->addDays(7);
            $this->user->save();

            $onTrial = $this->user->onGenericTrial();

            expect($onTrial)->toBeTrue();
        });
    });

    describe('trait usage', function (): void {
        it('uses the Billable trait', function (): void {
            $traits = class_uses_recursive($this->user);

            expect($traits)->toContain(Billable::class);
        });

        it('uses ManagesGateway through Billable', function (): void {
            $traits = class_uses_recursive($this->user);

            expect($traits)->toContain(AIArmada\Cashier\Concerns\ManagesGateway::class);
        });
    });
});
