<?php

declare(strict_types=1);

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\CashierChip\Subscription as ChipSubscription;
use AIArmada\CashierChip\SubscriptionItem as ChipSubscriptionItem;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentCashier\CustomerPortal\Pages\BillingOverview;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManageSubscriptions;
use AIArmada\FilamentCashier\CustomerPortal\Widgets\ActiveSubscriptionsWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('shows and manages CHIP subscriptions in the customer portal', function (): void {
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

    /** @var \Illuminate\Database\Eloquent\Model $user */
    $user = $userModel::query()->create([
        'name' => 'Subscriber',
        'email' => 'subscriber@example.com',
        'password' => bcrypt('secret'),
    ]);

    // Align owner context with the current customer.
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($user));

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
        'chip_price' => 'price_basic',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'trial_ends_at' => null,
        'next_billing_at' => Carbon::now()->addMonth(),
        'ends_at' => null,
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $graceId = (string) Str::uuid();
    ChipSubscription::query()->create([
        'id' => $graceId,
        'user_id' => (string) $user->getKey(),
        'type' => 'default',
        'chip_id' => 'sub_' . $graceId,
        'chip_status' => ChipSubscription::STATUS_CANCELED,
        'chip_price' => 'price_basic',
        'quantity' => 1,
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
        'trial_ends_at' => null,
        'next_billing_at' => null,
        'ends_at' => Carbon::now()->addDays(7),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    Auth::guard()->setUser($user);

    $overview = app(BillingOverview::class);
    expect($overview->getHeaderWidgetsColumns())->toBeArray();

    $widget = app(ActiveSubscriptionsWidget::class);
    expect($widget->getSubscriptions())->toHaveCount(2);

    $page = app(ManageSubscriptions::class);
    expect($page->getSubscriptions())->toHaveCount(2);

    $page->cancelSubscription('chip', $activeId);
    $page->cancelSubscription('chip', 'missing');

    $page->resumeSubscription('chip', $activeId);
    $page->resumeSubscription('chip', $graceId);

    $actionsMethod = new ReflectionMethod(ManageSubscriptions::class, 'getHeaderActions');
    $actionsMethod->setAccessible(true);
    expect($actionsMethod->invoke($page))->toBeArray();
});

it('limits customer portal subscriptions and can load more', function (): void {
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

    /** @var \Illuminate\Database\Eloquent\Model $user */
    $user = $userModel::query()->create([
        'name' => 'Subscriber',
        'email' => 'subscriber2@example.com',
        'password' => bcrypt('secret'),
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($user));

    CashierChip::useCustomerModel($userModel);
    CashierChip::useSubscriptionModel(ChipSubscription::class);
    CashierChip::useSubscriptionItemModel(ChipSubscriptionItem::class);

    $ids = [];
    foreach (range(1, 3) as $i) {
        $id = (string) Str::uuid();
        $ids[] = $id;

        ChipSubscription::query()->create([
            'id' => $id,
            'user_id' => (string) $user->getKey(),
            'type' => 'default',
            'chip_id' => 'sub_' . $id,
            'chip_status' => ChipSubscription::STATUS_ACTIVE,
            'chip_price' => 'price_basic',
            'quantity' => 1,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'trial_ends_at' => null,
            'next_billing_at' => Carbon::now()->addMonth(),
            'ends_at' => null,
            'created_at' => Carbon::now()->subDays($i),
            'updated_at' => Carbon::now()->subDays($i),
        ]);
    }

    Auth::guard()->setUser($user);

    $page = app(ManageSubscriptions::class);
    $page->perGatewayLimit = 1;

    expect($page->getSubscriptions())->toHaveCount(1);
    expect($page->hasMoreSubscriptions)->toBeTrue();

    $page->loadMoreSubscriptions(5);
    expect($page->getSubscriptions())->toHaveCount(3);
    expect($page->hasMoreSubscriptions)->toBeFalse();

    $widget = app(ActiveSubscriptionsWidget::class);
    $widget->perGatewayLimit = 1;
    expect($widget->getSubscriptions())->toHaveCount(1);
});
