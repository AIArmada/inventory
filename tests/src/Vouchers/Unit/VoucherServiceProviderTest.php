<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Services\VoucherValidator;
use AIArmada\Vouchers\Support\AffiliateIntegrationRegistrar;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Illuminate\Support\Facades\Event;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('VoucherServiceProvider', function (): void {
    describe('service bindings', function (): void {
        it('registers VoucherService as singleton', function (): void {
            $service1 = app(VoucherService::class);
            $service2 = app(VoucherService::class);

            expect($service1)->toBeInstanceOf(VoucherService::class)
                ->and($service1)->toBe($service2);
        });

        it('registers VoucherValidator as singleton', function (): void {
            $validator1 = app(VoucherValidator::class);
            $validator2 = app(VoucherValidator::class);

            expect($validator1)->toBeInstanceOf(VoucherValidator::class)
                ->and($validator1)->toBe($validator2);
        });

        it('registers VoucherRulesFactory as singleton', function (): void {
            $factory1 = app(VoucherRulesFactory::class);
            $factory2 = app(VoucherRulesFactory::class);

            expect($factory1)->toBeInstanceOf(VoucherRulesFactory::class)
                ->and($factory1)->toBe($factory2);
        });

        it('registers AffiliateIntegrationRegistrar as singleton', function (): void {
            $registrar1 = app(AffiliateIntegrationRegistrar::class);
            $registrar2 = app(AffiliateIntegrationRegistrar::class);

            expect($registrar1)->toBeInstanceOf(AffiliateIntegrationRegistrar::class)
                ->and($registrar1)->toBe($registrar2);
        });

        it('registers OwnerResolverInterface', function (): void {
            $resolver = app(OwnerResolverInterface::class);

            expect($resolver)->toBeInstanceOf(OwnerResolverInterface::class);
        });

        it('binds voucher facade accessor', function (): void {
            expect(app('voucher'))->toBeInstanceOf(VoucherService::class);
        });
    });

    describe('provides method', function (): void {
        it('returns list of provided services', function (): void {
            $provider = new AIArmada\Vouchers\VoucherServiceProvider(app());
            $provides = $provider->provides();

            expect($provides)->toContain(VoucherService::class)
                ->and($provides)->toContain(VoucherValidator::class)
                ->and($provides)->toContain(VoucherRulesFactory::class)
                ->and($provides)->toContain(AffiliateIntegrationRegistrar::class)
                ->and($provides)->toContain('voucher');
        });
    });

    describe('CartConditionResolver registration', function (): void {
        it('resolves VoucherCondition to CartCondition', function (): void {
            $resolver = app(CartConditionResolver::class);

            $voucherData = VoucherData::fromArray([
                'id' => 'test-123',
                'code' => 'TEST-COND-' . uniqid(),
                'name' => 'Test Condition',
                'type' => VoucherType::Fixed->value,
                'value' => 50,
                'currency' => 'MYR',
                'status' => VoucherStatus::Active->value,
            ]);

            $voucherCondition = new VoucherCondition($voucherData, order: 90, dynamic: false);

            $result = $resolver->resolve($voucherCondition);

            expect($result)->toBeInstanceOf(CartCondition::class);
        });

        it('resolves VoucherData to CartCondition', function (): void {
            $resolver = app(CartConditionResolver::class);

            $voucherData = VoucherData::fromArray([
                'id' => 'test-456',
                'code' => 'TEST-DATA-' . uniqid(),
                'name' => 'Test Data',
                'type' => VoucherType::Percentage->value,
                'value' => 10,
                'currency' => 'MYR',
                'status' => VoucherStatus::Active->value,
            ]);

            $result = $resolver->resolve($voucherData);

            expect($result)->toBeInstanceOf(CartCondition::class);
        });

        it('returns null for non-existent voucher code in array', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = [
                'voucher_code' => 'NON-EXISTENT-' . uniqid(),
            ];

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });

        it('returns null for non-existent voucher: prefixed string', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = 'voucher:NON-EXISTENT-' . uniqid();

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });

        it('returns null for empty voucher: prefix', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = 'voucher:';

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });

        it('returns null for array with empty code', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = [
                'voucher_code' => '',
            ];

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });
    });

    describe('event listeners', function (): void {
        it('registers VoucherApplied event listener', function (): void {
            $listeners = Event::getListeners(VoucherApplied::class);

            $hasListener = false;
            foreach ($listeners as $listener) {
                if (is_string($listener) && str_contains($listener, IncrementVoucherAppliedCount::class)) {
                    $hasListener = true;

                    break;
                }
                if (is_array($listener) && $listener[0] instanceof IncrementVoucherAppliedCount) {
                    $hasListener = true;

                    break;
                }
            }

            expect(count($listeners))->toBeGreaterThanOrEqual(1);
        });
    });
});
