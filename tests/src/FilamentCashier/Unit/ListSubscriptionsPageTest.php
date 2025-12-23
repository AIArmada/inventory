<?php

declare(strict_types=1);

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\CashierChip\Subscription as ChipSubscription;
use AIArmada\CashierChip\SubscriptionItem as ChipSubscriptionItem;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\ListSubscriptions;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

if (! function_exists('filamentCashier_setProtectedProperty')) {
    function filamentCashier_setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionObject($object);

        while (! $reflection->hasProperty($property) && ($parent = $reflection->getParentClass())) {
            $reflection = $parent;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}

it('lists CHIP subscriptions as unified subscriptions and applies tabs and filters', function (): void {
    if (! Schema::hasTable('cashier_chip_subscriptions')) {
        Schema::create('cashier_chip_subscriptions', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id');
            $table->nullableMorphs('owner');
            $table->string('type');
            $table->string('chip_id')->unique();
            $table->string('chip_status');
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('recurring_token')->nullable();
            $table->string('billing_interval')->default('month');
            $table->integer('billing_interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('cashier_chip_subscription_items')) {
        Schema::create('cashier_chip_subscription_items', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id');
            $table->nullableMorphs('owner');
            $table->string('chip_id')->unique();
            $table->string('chip_product')->nullable();
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();
        });
    }

    /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
    $userModel = config('auth.providers.users.model');
    $user = $userModel::query()->create([
        'name' => 'Subscriptions',
        'email' => 'subscriptions@example.com',
        'password' => bcrypt('secret'),
    ]);

    // Align owner context with the current customer.
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($user));

    Auth::guard()->setUser($user);

    CashierChip::useCustomerModel($userModel);
    CashierChip::useSubscriptionModel(ChipSubscription::class);
    CashierChip::useSubscriptionItemModel(ChipSubscriptionItem::class);

    $activeId = (string) Str::uuid();
    ChipSubscription::query()->create([
        'id' => $activeId,
        'user_id' => (string) $user->getKey(),
        'type' => 'default',
        'chip_id' => 'sub_' . $activeId,
        'chip_status' => ChipSubscription::STATUS_ACTIVE,
        'chip_price' => 'price_basic_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'trial_ends_at' => null,
        'next_billing_at' => Carbon::now()->addMonth(),
        'ends_at' => null,
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $canceledId = (string) Str::uuid();
    ChipSubscription::query()->create([
        'id' => $canceledId,
        'user_id' => (string) $user->getKey(),
        'type' => 'default',
        'chip_id' => 'sub_' . $canceledId,
        'chip_status' => ChipSubscription::STATUS_CANCELED,
        'chip_price' => 'price_basic_monthly',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'trial_ends_at' => null,
        'next_billing_at' => null,
        'ends_at' => Carbon::now()->addDays(5),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $page = app(ListSubscriptions::class);

    $tabs = $page->getTabs();
    expect($tabs)->toHaveKeys(['all', 'chip', 'active', 'issues']);

    $records = $page->getTableRecords();
    expect($records)->toHaveCount(2);
    expect($page->getTableRecordKey($records->first()))->toContain('chip-');
    expect($page->getTableRecordKey(['gateway' => 'chip', 'id' => 'abc']))->toBe('chip-abc');

    filamentCashier_setProtectedProperty($page, 'activeTab', 'active');
    expect($page->getTableRecords())->toHaveCount(2);

    filamentCashier_setProtectedProperty($page, 'activeTab', 'issues');
    expect($page->getTableRecords())->toHaveCount(0);

    filamentCashier_setProtectedProperty($page, 'activeTab', 'all');
    filamentCashier_setProtectedProperty($page, 'tableFilters', [
        'status' => ['value' => 'grace_period'],
    ]);
    expect($page->getTableRecords())->toHaveCount(1);
});
