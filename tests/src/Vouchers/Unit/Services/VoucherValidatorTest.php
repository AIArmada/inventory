<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Services\VoucherValidator;
use Illuminate\Database\Eloquent\Model;

describe('VoucherValidator', function (): void {
    beforeEach(function (): void {
        OwnerContext::clearOverride();
        app()->forgetInstance(OwnerResolverInterface::class);
    });

    /**
     * Helper to get a VoucherValidator instance from the container.
     */
    function makeVoucherValidator(): VoucherValidator
    {
        return app(VoucherValidator::class);
    }

    describe('normalizeCode method', function (): void {
        it('uppercases code when auto_uppercase is enabled', function (): void {
            config(['vouchers.code.auto_uppercase' => true]);
            config(['vouchers.owner.enabled' => false]);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('normalizeCode');
            $method->setAccessible(true);

            $result = $method->invoke($validator, ' testcode ');

            expect($result)->toBe('TESTCODE');
        });

        it('preserves case when auto_uppercase is disabled', function (): void {
            config(['vouchers.code.auto_uppercase' => false]);
            config(['vouchers.owner.enabled' => false]);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('normalizeCode');
            $method->setAccessible(true);

            $result = $method->invoke($validator, ' TestCode ');

            expect($result)->toBe('TestCode');
        });

        it('trims whitespace', function (): void {
            config(['vouchers.code.auto_uppercase' => true]);
            config(['vouchers.owner.enabled' => false]);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('normalizeCode');
            $method->setAccessible(true);

            $result = $method->invoke($validator, '   CODE123   ');

            expect($result)->toBe('CODE123');
        });
    });

    describe('resolveOwner method', function (): void {
        it('returns null when owner is disabled', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('resolveOwner');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBeNull();
        });

        it('calls resolver when owner is enabled', function (): void {
            config(['vouchers.owner.enabled' => true]);

            $mockOwner = Mockery::mock(Model::class);
            $ownerResolver = Mockery::mock(OwnerResolverInterface::class);
            $ownerResolver->shouldReceive('resolve')->once()->andReturn($mockOwner);

            app()->instance(OwnerResolverInterface::class, $ownerResolver);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('resolveOwner');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBe($mockOwner);
        });
    });

    describe('getCartTotal method', function (): void {
        it('gets total from cart object with method', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $cart = new class
            {
                public function getRawSubtotalWithoutConditions(): int
                {
                    return 15000;
                }
            };

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getCartTotal');
            $method->setAccessible(true);

            $result = $method->invoke($validator, $cart);

            expect($result)->toBe(15000);
        });

        it('gets total from array with total key', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $cart = ['total' => 20000];

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getCartTotal');
            $method->setAccessible(true);

            $result = $method->invoke($validator, $cart);

            expect($result)->toBe(20000);
        });

        it('returns zero for unknown format', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $cart = 'invalid';

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getCartTotal');
            $method->setAccessible(true);

            $result = $method->invoke($validator, $cart);

            expect($result)->toBe(0);
        });

        it('returns zero for array without total key', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $cart = ['items' => [], 'count' => 0];

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getCartTotal');
            $method->setAccessible(true);

            $result = $method->invoke($validator, $cart);

            expect($result)->toBe(0);
        });

        it('casts string total to int', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $cart = ['total' => '25000'];

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getCartTotal');
            $method->setAccessible(true);

            $result = $method->invoke($validator, $cart);

            expect($result)->toBe(25000);
        });
    });

    describe('constructor', function (): void {
        it('constructs via container', function (): void {
            $validator = makeVoucherValidator();

            expect($validator)->toBeInstanceOf(VoucherValidator::class);
        });
    });

    describe('VoucherValidationResult integration', function (): void {
        it('invalid result has correct properties', function (): void {
            $result = VoucherValidationResult::invalid('Test error');

            expect($result->isValid)->toBeFalse()
                ->and($result->reason)->toBe('Test error');
        });

        it('invalid result with context', function (): void {
            $result = VoucherValidationResult::invalid('Test error', ['key' => 'value']);

            expect($result->isValid)->toBeFalse()
                ->and($result->details)->toBe(['key' => 'value']);
        });

        it('valid result has correct properties', function (): void {
            $result = VoucherValidationResult::valid();

            expect($result)->toBeInstanceOf(VoucherValidationResult::class)
                ->and($result->isValid)->toBeTrue();
        });
    });

    describe('getUser method', function (): void {
        it('returns null when Auth user is null', function (): void {
            config(['vouchers.owner.enabled' => false]);
            Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn(null);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getUser');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBeNull();
        });

        it('returns null when Auth user is not a Model', function (): void {
            config(['vouchers.owner.enabled' => false]);

            // Use a non-Model object
            $nonModelUser = new stdClass;
            $nonModelUser->id = 123;

            Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($nonModelUser);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getUser');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBeNull();
        });

        it('returns Model when Auth user is a Model', function (): void {
            config(['vouchers.owner.enabled' => false]);

            $mockUser = Mockery::mock(Model::class);
            Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($mockUser);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getUser');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBe($mockUser);
        });
    });

    describe('getUserIdentifier method', function (): void {
        it('returns user id when authenticated', function (): void {
            config(['vouchers.owner.enabled' => false]);
            Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn(123);

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getUserIdentifier');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBe('123');
        });

        it('returns session id when not authenticated', function (): void {
            config(['vouchers.owner.enabled' => false]);
            Illuminate\Support\Facades\Auth::shouldReceive('id')->andReturn(null);
            Illuminate\Support\Facades\Session::shouldReceive('getId')->andReturn('session-abc123');

            $validator = makeVoucherValidator();

            $reflection = new ReflectionClass($validator);
            $method = $reflection->getMethod('getUserIdentifier');
            $method->setAccessible(true);

            $result = $method->invoke($validator);

            expect($result)->toBe('session-abc123');
        });
    });

    afterEach(function (): void {
        Mockery::close();
    });
});
