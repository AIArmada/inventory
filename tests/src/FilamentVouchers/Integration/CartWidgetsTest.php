<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

it('covers cart-related widgets and voucher suggestions', function (): void {
    config()->set('vouchers.owner.enabled', false);

    $cart = Cart::query()->create([
        'instance' => 'default',
        'identifier' => 'cart-1',
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    // Stub cart instance integration.
    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            return new class
            {
                public function getAppliedVouchers(): array
                {
                    $voucher = new stdClass;
                    $voucher->code = 'APPLIED';
                    $voucher->type = VoucherType::Fixed;
                    $voucher->value = 500;
                    $voucher->currency = 'USD';
                    $voucher->description = 'Applied voucher';

                    return [$voucher];
                }

                public function applyVoucher(string $code): void {}

                public function removeVoucher(string $code): void {}
            };
        }
    });

    $voucher = Voucher::query()->create([
        'code' => 'SAVE10',
        'name' => 'Save 10%',
        'type' => VoucherType::Percentage,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $tableWidget = app(AppliedVouchersWidget::class);
    $tableWidget->record = $cart;

    /** @var HasTable $livewire */
    $livewire = \Mockery::mock(HasTable::class);

    expect($tableWidget->table(Table::make($livewire)))->toBeInstanceOf(Table::class);

    $queryMethod = new ReflectionMethod(AppliedVouchersWidget::class, 'getAppliedVouchersQuery');
    $queryMethod->setAccessible(true);
    $collection = $queryMethod->invoke($tableWidget);

    expect($collection)->toBeIterable();

    $badges = app(AppliedVoucherBadgesWidget::class);
    $badges->record = $cart;

    $applied = $badges->getAppliedVouchers();
    expect($applied)->not->toBeEmpty();

    // Cover status detection branches.
    $statusMethod = new ReflectionMethod(AppliedVoucherBadgesWidget::class, 'determineVoucherStatus');
    $statusMethod->setAccessible(true);

    $expired = new stdClass;
    $expired->end_date = now()->subDay();

    expect($statusMethod->invoke($badges, $expired))->toBe('expired');

    $limitReached = new class
    {
        public int $usage_limit = 10;

        public function getRemainingUses(): int
        {
            return 0;
        }
    };

    expect($statusMethod->invoke($badges, $limitReached))->toBe('active');

    $action = $badges->removeVoucherAction('APPLIED');
    $badges->record = null;
    $fn = $action->getActionFunction();
    $fn?->__invoke();

    $quick = app(QuickApplyVoucherWidget::class);
    $quick->voucherCode = 'CODE';

    expect($quick->form(Schema::make($quick)))->toBeInstanceOf(Schema::class);

    // Non-cart branch
    $quick->record = null;
    $quick->applyVoucher();

    // Cart branch (VoucherException)
    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            return new class
            {
                public function applyVoucher(string $code): void
                {
                    throw new \AIArmada\Vouchers\Exceptions\VoucherException('Cannot apply');
                }
            };
        }
    });

    $quick->record = $cart;
    $quick->voucherCode = 'FAIL';
    $quick->applyVoucher();

    // Cart branch (Throwable)
    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            throw new RuntimeException('Boom');
        }
    });

    $quick->voucherCode = 'ERR';
    $quick->applyVoucher();

    // Cart branch (success)
    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            return new class
            {
                public function applyVoucher(string $code): void {}
            };
        }
    });

    $quick->voucherCode = 'OK';
    $quick->applyVoucher();
    expect($quick->voucherCode)->toBe('');

    $suggestions = app(VoucherSuggestionsWidget::class);
    $suggestions->record = $cart;

    $eligible = $suggestions->getEligibleVouchers();
    expect($eligible)->not->toBeEmpty();

    $calc = new ReflectionMethod(VoucherSuggestionsWidget::class, 'calculatePotentialSavings');
    $calc->setAccessible(true);

    $recommend = new ReflectionMethod(VoucherSuggestionsWidget::class, 'generateRecommendation');
    $recommend->setAccessible(true);

    $freeShipping = Voucher::make([
        'type' => VoucherType::FreeShipping,
        'value' => 0,
        'currency' => 'USD',
    ]);

    expect($calc->invoke($suggestions, $freeShipping, 10000))->toBe(0);
    expect($recommend->invoke($suggestions, $freeShipping, 10000, 0))->toContain('free shipping');

    $bigSavings = Voucher::make([
        'type' => VoucherType::Fixed,
        'value' => 5000,
        'currency' => 'USD',
    ]);

    expect($recommend->invoke($suggestions, $bigSavings, 10000, 5000))->toContain('Save');

    $expiring = Voucher::make([
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'USD',
        'expires_at' => now()->addDays(2),
    ]);

    expect($recommend->invoke($suggestions, $expiring, 10000, 100))->toContain('Expires');

    $limited = Voucher::query()->create([
        'code' => 'LIMITED',
        'name' => 'Limited',
        'type' => VoucherType::Fixed,
        'value' => 100,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
        'usage_limit' => 5,
    ]);

    expect($recommend->invoke($suggestions, $limited, 10000, 100))->toContain('uses left');

    $suggestions->applySuggestion($voucher->code);

    app()->instance(CartInstanceManager::class, new class
    {
        public function resolve(string $instance, string $identifier): object
        {
            return new class
            {
                public function applyVoucher(string $code): void
                {
                    throw new RuntimeException('Boom');
                }
            };
        }
    });

    $suggestions->applySuggestion($voucher->code);
});

it('prevents voucher suggestions widget cross-tenant cart access when owner scoping enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-voucher-suggestions@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-voucher-suggestions@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $cartOwnedByB = Cart::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
        'instance' => 'default',
        'identifier' => 'cart-owned-by-b',
        'currency' => 'USD',
        'subtotal' => 10000,
        'total' => 10000,
    ]);

    $voucher = Voucher::query()->create([
        'code' => 'OWNER-A-ONLY',
        'name' => 'Owner A Voucher',
        'type' => VoucherType::Fixed,
        'value' => 500,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $voucher->assignOwner($ownerA)->save();

    $manager = new class
    {
        public int $calls = 0;

        public function resolve(string $instance, string $identifier): object
        {
            $this->calls++;

            return new class
            {
                public function getAppliedVouchers(): array
                {
                    return [];
                }

                public function applyVoucher(string $code): void {}
            };
        }
    };

    app()->instance(CartInstanceManager::class, $manager);

    $suggestions = app(VoucherSuggestionsWidget::class);
    $suggestions->record = $cartOwnedByB;

    expect($suggestions->getEligibleVouchers())->toBeEmpty();
    $suggestions->applySuggestion($voucher->code);

    expect($manager->calls)->toBe(0);
});
