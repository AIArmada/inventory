<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\User;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

uses(TestCase::class);

function bindFilamentCashierChipOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('scopes SubscriptionResource queries to the current owner', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-cashier-chip-owner-a-xt@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-cashier-chip-owner-b-xt@example.com',
    ]);

    bindFilamentCashierChipOwner($ownerB);

    $subscriptionB = Subscription::query()->create([
        // Safe fast-path: billable == owner for strict owner validation.
        'user_id' => $ownerB->id,
        'type' => 'default',
        'chip_id' => 'sub_' . Str::random(40),
        'chip_status' => Subscription::STATUS_ACTIVE,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'recurring_token' => 'tok_' . Str::random(32),
        'next_billing_at' => now()->addMonth(),
    ]);

    bindFilamentCashierChipOwner($ownerA);

    expect(SubscriptionResource::getEloquentQuery()->whereKey($subscriptionB->id)->exists())->toBeFalse();
    expect(SubscriptionResource::getNavigationBadge())->toBeNull();
});

it('scopes InvoiceResource queries even if chip owner scoping is disabled', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('chip.owner.enabled', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-cashier-chip-owner-a-invoice@example.com',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-cashier-chip-owner-b-invoice@example.com',
    ]);

    $purchaseA = Purchase::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'purchase',
        'created_on' => now()->getTimestamp(),
        'updated_on' => now()->getTimestamp(),
        'client' => ['email' => 'a@example.com'],
        'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        'brand_id' => (string) Str::uuid(),
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
        'status' => 'paid',
    ]);
    $purchaseA->assignOwner($ownerA)->save();

    $purchaseB = Purchase::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'purchase',
        'created_on' => now()->getTimestamp(),
        'updated_on' => now()->getTimestamp(),
        'client' => ['email' => 'b@example.com'],
        'purchase' => ['total' => 2000, 'currency' => 'MYR'],
        'brand_id' => (string) Str::uuid(),
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
        'status' => 'paid',
    ]);
    $purchaseB->assignOwner($ownerB)->save();

    bindFilamentCashierChipOwner($ownerA);

    expect(InvoiceResource::getEloquentQuery()->whereKey($purchaseA->id)->exists())->toBeTrue();
    expect(InvoiceResource::getEloquentQuery()->whereKey($purchaseB->id)->exists())->toBeFalse();
});

it('fails closed when owner scoping is enabled but no owner can be resolved', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'filament-cashier-chip-owner-null@example.com',
    ]);

    $subscription = Subscription::query()->create([
        // Safe fast-path: billable == owner for strict owner validation.
        'user_id' => $owner->id,
        'type' => 'default',
        'chip_id' => 'sub_' . Str::random(40),
        'chip_status' => Subscription::STATUS_ACTIVE,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'recurring_token' => 'tok_' . Str::random(32),
        'next_billing_at' => now()->addMonth(),
    ]);

    $purchase = Purchase::query()->create([
        'id' => (string) Str::uuid(),
        'type' => 'purchase',
        'created_on' => now()->getTimestamp(),
        'updated_on' => now()->getTimestamp(),
        'client' => ['email' => 'null-owner@example.com'],
        'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        'brand_id' => (string) Str::uuid(),
        'issuer_details' => [],
        'transaction_data' => [],
        'status_history' => [],
        'status' => 'paid',
    ]);
    $purchase->assignOwner($owner)->save();

    bindFilamentCashierChipOwner(null);

    expect(SubscriptionResource::getEloquentQuery()->whereKey($subscription->id)->exists())->toBeFalse();
    expect(InvoiceResource::getEloquentQuery()->whereKey($purchase->id)->exists())->toBeFalse();
});
