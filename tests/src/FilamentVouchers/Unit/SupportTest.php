<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Models\Voucher as FilamentVoucher;
use AIArmada\FilamentVouchers\Support\ConditionTargetPreset;
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;
use AIArmada\FilamentVouchers\Support\MoneyHelper;
use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('exposes condition target presets', function (): void {
    expect(ConditionTargetPreset::default())->toBe(ConditionTargetPreset::CartSubtotal);
    expect(ConditionTargetPreset::options())->toHaveKeys([
        'cart_subtotal',
        'grand_total',
        'shipments',
        'taxable',
        'items',
        'custom',
    ]);

    expect(ConditionTargetPreset::detect(null))->toBeNull();
    expect(ConditionTargetPreset::detect(''))->toBeNull();
    expect(ConditionTargetPreset::detect('not-a-dsl'))->toBeNull();

    expect(ConditionTargetPreset::CartSubtotal->dsl())->toBeString();
    expect(ConditionTargetPreset::Custom->dsl())->toBeNull();
});

it('handles filament cart integration in both available and unavailable environments', function (): void {
    $bridge = new FilamentCartBridge;

    expect($bridge->resolveCartUrl(null))->toBeNull();
    expect($bridge->resolveCartUrl(''))->toBeNull();
    expect($bridge->isWarmed())->toBeFalse();

    if ($bridge->isAvailable()) {
        expect($bridge->getCartModel())->toBeString();
        expect($bridge->getCartResource())->toBeString();

        $bridge->warm();
        expect($bridge->isWarmed())->toBeTrue();

        // Test cart stats when available
        $stats = $bridge->getVoucherCartStats();
        expect($stats)->toHaveKeys(['active_carts_with_vouchers', 'total_potential_discount']);

        return;
    }

    expect($bridge->getCartModel())->toBeNull();
    expect($bridge->getCartResource())->toBeNull();
    expect($bridge->findCart('non-existent'))->toBeNull();
    expect($bridge->countCartsWithVoucher('TEST'))->toBe(0);
    expect($bridge->getVoucherCartStats())->toBe([
        'active_carts_with_vouchers' => 0,
        'total_potential_discount' => 0,
    ]);
});

it('resolves voucher owner display labels via OwnerTypeRegistry', function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('display_name')->nullable();
        $table->timestamps();
    });

    config()->set('filament-vouchers.owners', [
        [
            'model' => User::class,
            'label' => 'Users',
            'title_attribute' => 'name',
            'subtitle_attribute' => 'email',
            'search_attributes' => ['name', 'email'],
        ],
    ]);

    $registry = new OwnerTypeRegistry;

    expect($registry->hasDefinitions())->toBeTrue();
    expect($registry->options())->toHaveKey(User::class);

    $user = User::query()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    $results = $registry->search(User::class, 'Alice');
    expect($results)->toHaveKey($user->getKey());

    $label = $registry->resolveLabelForKey(User::class, $user->getKey());
    expect($label)->toContain('Alice');

    $voucher = FilamentVoucher::query()->create([
        'code' => 'TEST-OWNER-LABEL',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $voucher->assignOwner($user)->save();

    expect($voucher->owner_display_name)->toContain('Alice');
});

it('converts cents to display format and back', function (): void {
    // Cents to display
    expect(MoneyHelper::centsToDisplay(null))->toBeNull();
    expect(MoneyHelper::centsToDisplay(0))->toBe('0.00');
    expect(MoneyHelper::centsToDisplay(100))->toBe('1.00');
    expect(MoneyHelper::centsToDisplay(1000))->toBe('10.00');
    expect(MoneyHelper::centsToDisplay(1999))->toBe('19.99');
    expect(MoneyHelper::centsToDisplay(12345))->toBe('123.45');

    // Display to cents
    expect(MoneyHelper::displayToCents(null))->toBeNull();
    expect(MoneyHelper::displayToCents(''))->toBeNull();
    expect(MoneyHelper::displayToCents('0'))->toBe(0);
    expect(MoneyHelper::displayToCents('1.00'))->toBe(100);
    expect(MoneyHelper::displayToCents('10.00'))->toBe(1000);
    expect(MoneyHelper::displayToCents('19.99'))->toBe(1999);
    expect(MoneyHelper::displayToCents('123.45'))->toBe(12345);
});

it('converts basis points to display percentage and back', function (): void {
    // Basis points to display
    expect(MoneyHelper::basisPointsToDisplay(null))->toBeNull();
    expect(MoneyHelper::basisPointsToDisplay(0))->toBe('0.00');
    expect(MoneyHelper::basisPointsToDisplay(100))->toBe('1.00');
    expect(MoneyHelper::basisPointsToDisplay(1000))->toBe('10.00');
    expect(MoneyHelper::basisPointsToDisplay(1050))->toBe('10.50');
    expect(MoneyHelper::basisPointsToDisplay(2575))->toBe('25.75');

    // Display to basis points
    expect(MoneyHelper::displayToBasisPoints(null))->toBeNull();
    expect(MoneyHelper::displayToBasisPoints(''))->toBeNull();
    expect(MoneyHelper::displayToBasisPoints('0'))->toBe(0);
    expect(MoneyHelper::displayToBasisPoints('1.00'))->toBe(100);
    expect(MoneyHelper::displayToBasisPoints('10.00'))->toBe(1000);
    expect(MoneyHelper::displayToBasisPoints('10.50'))->toBe(1050);
    expect(MoneyHelper::displayToBasisPoints('25.75'))->toBe(2575);
});

it('formats money with Akaunting Money', function (): void {
    $formatted = MoneyHelper::formatMoney(1000, 'USD');
    expect($formatted)->toContain('10');

    $formatted = MoneyHelper::formatMoney(1999, 'MYR');
    expect($formatted)->toContain('19');
});

it('formats percentages correctly', function (): void {
    expect(MoneyHelper::formatPercentage(1000))->toBe('10%');
    expect(MoneyHelper::formatPercentage(1050))->toBe('10.5%');
    expect(MoneyHelper::formatPercentage(2575))->toBe('25.75%');
    expect(MoneyHelper::formatPercentage(100))->toBe('1%');
    expect(MoneyHelper::formatPercentage(0))->toBe('0%');
});

it('provides default currency from config', function (): void {
    config()->set('filament-vouchers.default_currency', 'SGD');
    expect(MoneyHelper::defaultCurrency())->toBe('SGD');

    config()->set('filament-vouchers.default_currency', 'myr');
    expect(MoneyHelper::defaultCurrency())->toBe('MYR');
});
