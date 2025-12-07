<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;
use AIArmada\FilamentCart\Models\Cart as SnapshotCart;
use AIArmada\FilamentVouchers\Models\Voucher;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('cart_snapshots');
    Schema::create('cart_snapshots', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('identifier');
        $table->string('instance')->default('default');
        $table->json('items')->nullable();
        $table->json('conditions')->nullable();
        $table->json('metadata')->nullable();
        $table->unsignedInteger('items_count')->default(0);
        $table->unsignedInteger('quantity')->default(0);
        $table->bigInteger('subtotal')->default(0);
        $table->bigInteger('total')->default(0);
        $table->bigInteger('savings')->default(0);
        $table->string('currency', 3)->default('USD');

        // AI/Analytics columns
        $table->timestamp('last_activity_at')->nullable();
        $table->timestamp('checkout_started_at')->nullable();
        $table->timestamp('checkout_abandoned_at')->nullable();
        $table->unsignedTinyInteger('recovery_attempts')->default(0);
        $table->timestamp('recovered_at')->nullable();

        // Collaborative Cart Support
        $table->boolean('is_collaborative')->default(false);
        $table->unsignedSmallInteger('collaborator_count')->default(0);

        $table->timestamps();
    });
});

test('cart bridge resolves urls when integration enabled', function (): void {
    $cart = SnapshotCart::create([
        'identifier' => 'bridge-user',
        'instance' => 'default',
        'subtotal' => 100,
        'total' => 90,
    ]);

    $url = app(CartBridge::class)->resolveUrl('bridge-user');

    expect($cart)->toBeInstanceOf(SnapshotCart::class)
        ->and($url)->not()->toBeNull();
});

test('cart bridge honours config toggle', function (): void {
    config(['filament-affiliates.integrations.filament_cart' => false]);

    expect(app(CartBridge::class)->isAvailable())->toBeFalse();

    config(['filament-affiliates.integrations.filament_cart' => true]);
});

test('voucher bridge resolves urls for vouchers when enabled', function (): void {
    $voucher = Voucher::create([
        'code' => 'BRIDGE-VOUCHER',
        'name' => 'Bridge Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
    ]);

    $url = app(VoucherBridge::class)->resolveUrl('BRIDGE-VOUCHER');

    expect($voucher->code)->toBe('BRIDGE-VOUCHER')
        ->and($url)->not()->toBeNull();
});

test('voucher bridge can be disabled via config', function (): void {
    config(['filament-affiliates.integrations.filament_vouchers' => false]);

    expect(app(VoucherBridge::class)->isAvailable())->toBeFalse();

    config(['filament-affiliates.integrations.filament_vouchers' => true]);
});
