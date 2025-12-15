<?php

declare(strict_types=1);

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;

uses(CashierTestCase::class);

describe('Cashier Class - Additional Coverage', function (): void {
    beforeEach(function (): void {
        // Reset static properties
        Cashier::$registersRoutes = true;
    });

    describe('gateway', function (): void {
        it('returns gateway via static method', function (): void {
            $gateway = Cashier::gateway();

            expect($gateway)->toBeInstanceOf(AIArmada\Cashier\Contracts\GatewayContract::class);
        });

        it('returns specific gateway via static method', function (): void {
            $gateway = Cashier::gateway('chip');

            expect($gateway->name())->toBe('chip');
        });
    });

    describe('manager', function (): void {
        it('returns GatewayManager instance', function (): void {
            $manager = Cashier::manager();

            expect($manager)->toBeInstanceOf(GatewayManager::class);
        });
    });

    describe('supportedGateways', function (): void {
        it('is alias for availableGateways', function (): void {
            $supported = Cashier::supportedGateways();
            $available = Cashier::availableGateways();

            expect($supported)->toBe($available);
        });
    });

    describe('formatCurrencyUsing', function (): void {
        it('accepts custom currency formatter', function (): void {
            Cashier::formatCurrencyUsing(function (int $amount, ?string $currency, ?string $locale) {
                return 'CUSTOM:' . $amount . ':' . ($currency ?? 'USD');
            });

            $formatted = Cashier::formatAmount(1000, 'EUR');

            expect($formatted)->toBe('CUSTOM:1000:EUR');
        });
    });

    describe('formatAmount', function (): void {
        it('formats amount with default currency', function (): void {
            // Use a fresh formatter
            Cashier::formatCurrencyUsing(fn ($a, $c, $l) => '$' . number_format($a / 100, 2));

            $formatted = Cashier::formatAmount(1000);

            expect($formatted)->toBe('$10.00');
        });

        it('formats amount with specific currency', function (): void {
            Cashier::formatCurrencyUsing(fn ($a, $c, $l) => $c . ' ' . number_format($a / 100, 2));

            $formatted = Cashier::formatAmount(5000, 'MYR');

            expect($formatted)->toBe('MYR 50.00');
        });

        it('formats amount with specific locale', function (): void {
            Cashier::formatCurrencyUsing(fn ($a, $c, $l) => "[$l] $c $a");

            $formatted = Cashier::formatAmount(10000, 'USD', 'en_US');

            expect($formatted)->toBe('[en_US] USD 10000');
        });
    });

    describe('deactivatePastDue', function (): void {
        it('syncs to underlying packages when available', function (): void {
            Cashier::deactivatePastDue(true);

            expect(Cashier::$deactivatePastDue)->toBeTrue();
        });
    });

    describe('deactivateIncomplete', function (): void {
        it('syncs to underlying packages when available', function (): void {
            Cashier::deactivateIncomplete(true);

            expect(Cashier::$deactivateIncomplete)->toBeTrue();
        });
    });

    describe('useCustomerModel', function (): void {
        it('sets customer model class', function (): void {
            Cashier::useCustomerModel(User::class);

            expect(Cashier::$customerModel)->toBe(User::class);
        });
    });
});
