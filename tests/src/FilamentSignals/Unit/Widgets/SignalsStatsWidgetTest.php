<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentSignals\Pages\SignalsDashboard;
use AIArmada\FilamentSignals\Widgets\EventTrendWidget;
use AIArmada\FilamentSignals\Widgets\PendingSignalAlertsWidget;
use AIArmada\FilamentSignals\Widgets\SignalsStatsWidget;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;

uses(FilamentSignalsTestCase::class);

function filamentSignals_invokeProtected(object $instance, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($instance, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($instance, $arguments);
}

it('renders stats and chart data for the current owner', function (): void {
    config()->set('filament-signals.resources.labels.outcomes', 'Registrations');
    config()->set('filament-signals.resources.labels.monetary_value', 'Donations');

    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'widgets-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'widgets-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Signals A',
        'slug' => 'signals-a',
        'write_key' => 'widget-key-a',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Signals B',
        'slug' => 'signals-b',
        'write_key' => 'widget-key-b',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $identityA = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'identity-a',
        'last_seen_at' => CarbonImmutable::now(),
    ]);
    $identityA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'identity-b',
        'last_seen_at' => CarbonImmutable::now(),
    ]);
    $identityB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $sessionA = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA->id,
        'session_identifier' => 'widget-session-a',
        'started_at' => CarbonImmutable::now(),
    ]);
    $sessionA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'session_identifier' => 'widget-session-b',
        'started_at' => CarbonImmutable::now(),
    ]);
    $sessionB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $eventA = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => 'purchase_completed',
        'event_category' => 'conversion',
        'revenue_minor' => 5000,
    ]);
    $eventA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $eventB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::now(),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);
    $eventB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $metricA = SignalDailyMetric::query()->create([
        'tracked_property_id' => $propertyA->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'unique_identities' => 1,
        'sessions' => 1,
        'bounced_sessions' => 0,
        'page_views' => 0,
        'events' => 1,
        'conversions' => 1,
        'revenue_minor' => 5000,
    ]);
    $metricA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $metricB = SignalDailyMetric::query()->create([
        'tracked_property_id' => $propertyB->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'unique_identities' => 1,
        'sessions' => 1,
        'bounced_sessions' => 0,
        'page_views' => 1,
        'events' => 1,
        'conversions' => 0,
        'revenue_minor' => 0,
    ]);
    $metricB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $ruleA = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyA->id,
        'name' => 'Critical revenue threshold',
        'slug' => 'critical-revenue-threshold',
        'metric_key' => 'revenue_minor',
        'operator' => 'gte',
        'threshold' => 1000,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'critical',
        'priority' => 80,
        'is_active' => true,
    ]);
    $ruleA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $ruleB = SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyB->id,
        'name' => 'Other owner threshold',
        'slug' => 'other-owner-threshold',
        'metric_key' => 'events',
        'operator' => 'gte',
        'threshold' => 5,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 30,
        'severity' => 'warning',
        'priority' => 40,
        'is_active' => true,
    ]);
    $ruleB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $alertA = SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleA->id,
        'tracked_property_id' => $propertyA->id,
        'metric_key' => 'revenue_minor',
        'operator' => 'gte',
        'metric_value' => 5000,
        'threshold_value' => 1000,
        'severity' => 'critical',
        'title' => 'Revenue alert',
        'message' => 'Revenue crossed the threshold.',
        'is_read' => false,
        'created_at' => CarbonImmutable::now(),
        'updated_at' => CarbonImmutable::now(),
    ]);
    $alertA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $alertB = SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleB->id,
        'tracked_property_id' => $propertyB->id,
        'metric_key' => 'events',
        'operator' => 'gte',
        'metric_value' => 8,
        'threshold_value' => 5,
        'severity' => 'warning',
        'title' => 'Other owner alert',
        'message' => 'This should not be visible.',
        'is_read' => false,
        'created_at' => CarbonImmutable::now(),
        'updated_at' => CarbonImmutable::now(),
    ]);
    $alertB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $statsWidget = app(SignalsStatsWidget::class);
    $stats = filamentSignals_invokeProtected($statsWidget, 'getStats');

    expect($stats)->toHaveCount(8)
        ->and($stats[6]->getLabel())->toBe('Registrations')
        ->and($stats[7]->getLabel())->toBe('Donations')
        ->and($stats[0]->getValue())->toBe('1')
        ->and($stats[1]->getValue())->toBe('1')
        ->and($stats[2]->getValue())->toBe('1')
        ->and($stats[7]->getValue())->toBe('MYR 50.00');

    $chartWidget = app(EventTrendWidget::class);
    $data = filamentSignals_invokeProtected($chartWidget, 'getData');

    expect($data['datasets'][0]['data'])->toBe([1])
        ->and($data['datasets'][1]['label'])->toBe('Registrations')
        ->and($data['datasets'][1]['data'])->toBe([1])
        ->and(filamentSignals_invokeProtected($chartWidget, 'getType'))->toBe('line');
});

it('registers the pending alerts widget on the dashboard', function (): void {
    $dashboard = app(SignalsDashboard::class);

    expect($dashboard->getWidgets())
        ->toContain(SignalsStatsWidget::class)
        ->toContain(EventTrendWidget::class)
        ->toContain(PendingSignalAlertsWidget::class);
});

it('can instantiate the pending alerts widget', function (): void {
    $widget = new PendingSignalAlertsWidget;

    expect($widget)->toBeInstanceOf(PendingSignalAlertsWidget::class);
});
