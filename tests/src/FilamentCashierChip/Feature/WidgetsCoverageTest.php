<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;
use AIArmada\FilamentCashierChip\Widgets\AttentionRequiredWidget;
use AIArmada\FilamentCashierChip\Widgets\ChurnRateWidget;
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentCashierChip\Widgets\SubscriptionDistributionWidget;
use AIArmada\FilamentCashierChip\Widgets\TrialConversionsWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

uses(TestCase::class);

function filamentCashierChip_createSubscriptionWithItem(Model $owner, array $subscriptionAttributes = [], array $itemAttributes = []): Subscription
{
    /** @var Subscription $subscription */
    $subscription = OwnerContext::withOwner($owner, function () use ($owner, $subscriptionAttributes, $itemAttributes): Subscription {
        $subscription = Subscription::create(array_merge([
            'owner_type' => $owner::class,
            'owner_id' => (string) $owner->getKey(),
            'user_id' => (string) ($subscriptionAttributes['user_id'] ?? 1),
            'type' => 'default',
            'chip_id' => 'sub_' . Str::uuid()->toString(),
            'chip_status' => Subscription::STATUS_ACTIVE,
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $subscriptionAttributes));

        SubscriptionItem::create(array_merge([
            'owner_type' => $owner::class,
            'owner_id' => (string) $owner->getKey(),
            'subscription_id' => $subscription->id,
            'chip_id' => 'item_' . Str::uuid()->toString(),
            'chip_price' => 'price_basic',
            'quantity' => 1,
            'unit_amount' => 10_00,
        ], $itemAttributes));

        return $subscription;
    });

    return $subscription->refresh();
}

it('covers all dashboard widgets code paths', function (): void {
    $user = $this->createUser();

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_ACTIVE,
        'created_at' => now()->subMonths(2),
    ], [
        'unit_amount' => 25_00,
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_ACTIVE,
        'created_at' => now()->subDays(2),
        'coupon_id' => 'coupon_test',
        'coupon_discount' => 5_00,
    ], [
        'unit_amount' => 30_00,
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(2),
        'created_at' => now()->subDays(7),
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_PAST_DUE,
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_INCOMPLETE,
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_UNPAID,
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_CANCELED,
        'ends_at' => now()->addDays(2),
    ]);

    filamentCashierChip_createSubscriptionWithItem($user, [
        'user_id' => (string) $user->getKey(),
        'chip_status' => Subscription::STATUS_ACTIVE,
        'created_at' => now()->subMonths(1)->startOfMonth()->addDay(),
        'trial_ends_at' => now()->subMonth()->endOfMonth()->subDay(),
    ]);

    $statsWidgets = [
        ActiveSubscribersWidget::class,
        MRRWidget::class,
        ChurnRateWidget::class,
        TrialConversionsWidget::class,
        AttentionRequiredWidget::class,
    ];

    foreach ($statsWidgets as $widgetClass) {
        $widget = app($widgetClass);
        $method = new ReflectionMethod($widgetClass, 'getStats');
        $method->setAccessible(true);

        $stats = $method->invoke($widget);

        expect($stats)->toBeArray();
        expect($stats)->not()->toBeEmpty();
        expect($stats[0])->toBeInstanceOf(Stat::class);
    }

    $mrrWidget = app(MRRWidget::class);
    $normalizeMethod = new ReflectionMethod(MRRWidget::class, 'normalizeToMonthly');
    $normalizeMethod->setAccessible(true);
    expect($normalizeMethod->invoke($mrrWidget, 10_00, 'month', 1))->toBeInt();
    expect($normalizeMethod->invoke($mrrWidget, 10_00, 'week', 1))->toBeInt();
    expect($normalizeMethod->invoke($mrrWidget, 10_00, 'year', 1))->toBeInt();
    expect($normalizeMethod->invoke($mrrWidget, 10_00, 'unknown', 1))->toBeInt();

    $activeWidget = app(ActiveSubscribersWidget::class);
    $trendMethod = new ReflectionMethod(ActiveSubscribersWidget::class, 'calculateTrend');
    $trendMethod->setAccessible(true);
    expect($trendMethod->invoke($activeWidget, 10, 5))->toBeArray();
    expect($trendMethod->invoke($activeWidget, 5, 10))->toBeArray();
    expect($trendMethod->invoke($activeWidget, 10, 10))->toBeArray();

    $churnWidget = app(ChurnRateWidget::class);
    $colorMethod = new ReflectionMethod(ChurnRateWidget::class, 'getChurnColor');
    $colorMethod->setAccessible(true);
    expect($colorMethod->invoke($churnWidget, 1.0))->toBe('success');
    expect($colorMethod->invoke($churnWidget, 3.0))->toBe('warning');
    expect($colorMethod->invoke($churnWidget, 6.0))->toBe('danger');

    $chartWidgets = [
        RevenueChartWidget::class,
        SubscriptionDistributionWidget::class,
    ];

    foreach ($chartWidgets as $widgetClass) {
        $widget = app($widgetClass);
        $dataMethod = new ReflectionMethod($widgetClass, 'getData');
        $dataMethod->setAccessible(true);
        $optionsMethod = new ReflectionMethod($widgetClass, 'getOptions');
        $optionsMethod->setAccessible(true);
        $typeMethod = new ReflectionMethod($widgetClass, 'getType');
        $typeMethod->setAccessible(true);

        $data = $dataMethod->invoke($widget);
        $options = $optionsMethod->invoke($widget);
        $type = $typeMethod->invoke($widget);

        expect($data)->toBeArray()->and($data)->toHaveKeys(['datasets', 'labels']);
        expect($options)->toBeArray();
        expect($type)->toBeString();
    }

    $chartMethod = new ReflectionMethod(RevenueChartWidget::class, 'normalizeToMonthly');
    $chartMethod->setAccessible(true);
    $revenueWidget = app(RevenueChartWidget::class);
    expect($chartMethod->invoke($revenueWidget, 10_00, 'day', 1))->toBeInt();
    expect($chartMethod->invoke($revenueWidget, 10_00, 'week', 1))->toBeInt();
    expect($chartMethod->invoke($revenueWidget, 10_00, 'month', 1))->toBeInt();
    expect($chartMethod->invoke($revenueWidget, 10_00, 'year', 1))->toBeInt();
    expect($chartMethod->invoke($revenueWidget, 10_00, 'unknown', 1))->toBeInt();

    $conversionWidget = app(TrialConversionsWidget::class);
    $trendDescriptionMethod = new ReflectionMethod(TrialConversionsWidget::class, 'getTrendDescription');
    $trendDescriptionMethod->setAccessible(true);
    expect($trendDescriptionMethod->invoke($conversionWidget, 10.0, 10.0))->toBeString();
    expect($trendDescriptionMethod->invoke($conversionWidget, 12.0, 10.0))->toBeString();
    expect($trendDescriptionMethod->invoke($conversionWidget, 8.0, 10.0))->toBeString();

    $attentionWidget = app(AttentionRequiredWidget::class);
    $descriptionMethod = new ReflectionMethod(AttentionRequiredWidget::class, 'buildDescription');
    $descriptionMethod->setAccessible(true);
    expect($descriptionMethod->invoke($attentionWidget, 0, 0, 0, 0, 0))->toBe('All subscriptions healthy');
    expect($descriptionMethod->invoke($attentionWidget, 1, 2, 3, 4, 5))->toBeString();
});
