<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Gateways\AbstractGateway;
use AIArmada\Cashier\Gateways\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('Gateways', function (): void {
    describe('AbstractGateway', function (): void {
        it('is an abstract class implementing GatewayContract', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);

            expect($reflection->isAbstract())->toBeTrue()
                ->and($reflection->implementsInterface(GatewayContract::class))->toBeTrue();
        });

        it('defines name as abstract method', function (): void {
            $reflection = new ReflectionClass(AbstractGateway::class);
            $method = $reflection->getMethod('name');

            expect($method->isAbstract())->toBeTrue();
        });

        it('provides currency method', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->currency())->toBe('USD');
        });
    });

    describe('StripeGateway', function (): void {
        it('returns correct name', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->name())->toBe('stripe');
        });

        it('extends AbstractGateway', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway)->toBeInstanceOf(AbstractGateway::class);
        });

        it('implements GatewayContract', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('returns correct currency', function (): void {
            $gateway = $this->gatewayManager->gateway('stripe');

            expect($gateway->currency())->toBe('USD');
        });

        it('fails closed when webhook secret is missing', function (): void {
            $gateway = new StripeGateway(['webhook_secret' => null]);

            $result = $gateway->verifyWebhookSignature('{"id":"evt_test"}', [
                'Stripe-Signature' => 't=1,v1=abc',
            ]);

            expect($result)->toBeFalse();
        });

        it('fails closed when signature header is missing', function (): void {
            $gateway = new StripeGateway(['webhook_secret' => 'whsec_test']);

            $result = $gateway->verifyWebhookSignature('{"id":"evt_test"}', []);

            expect($result)->toBeFalse();
        });
    });

    describe('ChipGateway', function (): void {
        it('returns correct name', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->name())->toBe('chip');
        });

        it('extends AbstractGateway', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway)->toBeInstanceOf(AbstractGateway::class);
        });

        it('implements GatewayContract', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway)->toBeInstanceOf(GatewayContract::class);
        });

        it('returns correct currency', function (): void {
            $gateway = $this->gatewayManager->gateway('chip');

            expect($gateway->currency())->toBe('MYR');
        });
    });
});
