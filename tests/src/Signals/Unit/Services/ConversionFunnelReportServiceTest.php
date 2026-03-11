<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\ConversionFunnelReportService;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds an owner-safe conversion funnel summary', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Funnel Owner A',
        'email' => 'funnel-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Funnel Owner B',
        'email' => 'funnel-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Funnel Property',
        'slug' => 'owner-a-funnel-property',
        'write_key' => 'owner-a-funnel-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Funnel Property',
        'slug' => 'owner-b-funnel-property',
        'write_key' => 'owner-b-funnel-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $identityA1 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'owner-a-customer-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);
    $identityA1->assignOwner($ownerA)->save();

    $identityA2 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'owner-a-customer-2',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);
    $identityA2->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'owner-b-customer-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 12:00:00'),
    ]);
    $identityB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $startedA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
    ]);
    $startedA1->assignOwner($ownerA)->save();

    $startedA2 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA2->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 11:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/checkout',
    ]);
    $startedA2->assignOwner($ownerA)->save();

    $completedA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:15:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
    ]);
    $completedA1->assignOwner($ownerA)->save();

    $paidA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:20:00'),
        'event_name' => 'conversion.completed',
        'event_category' => 'conversion',
        'revenue_minor' => 12900,
        'path' => '/pricing',
    ]);
    $paidA1->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $startedB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 12:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'path' => '/pricing',
    ]);
    $startedB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(ConversionFunnelReportService::class);
    $summary = $service->summary($propertyA->id, '2026-03-10', '2026-03-10');
    $stages = $service->stages($propertyA->id, '2026-03-10', '2026-03-10');

    expect($summary['started'])->toBe(2)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['paid'])->toBe(1)
        ->and($summary['start_to_complete_rate'])->toBe(50.0)
        ->and($summary['complete_to_paid_rate'])->toBe(100.0)
        ->and($summary['overall_rate'])->toBe(50.0)
        ->and($summary['start_drop_off'])->toBe(1)
        ->and($summary['complete_drop_off'])->toBe(0)
        ->and($summary['revenue_minor'])->toBe(12900)
        ->and($stages)->toHaveCount(3)
        ->and($summary['started_label'])->toBe('Visited')
        ->and($summary['completed_label'])->toBe('Explored Further')
        ->and($summary['paid_label'])->toBe('Completed Outcome')
        ->and($stages[2]['label'])->toBe('Completed Outcome')
        ->and($stages[2]['revenue_minor'])->toBe(12900)
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Funnel Property']);
});

it('applies signal segments to conversion funnel metrics', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Funnel Segment Owner',
        'email' => 'funnel-segment-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Funnel Segment Property',
        'slug' => 'funnel-segment-property',
        'write_key' => 'funnel-segment-key',
    ]);

    $pricingIdentity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'pricing-checkout-customer',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:30:00'),
    ]);

    $checkoutIdentity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'checkout-direct-customer',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 11:30:00'),
    ]);

    foreach ([
        ['identity' => $pricingIdentity->id, 'event_name' => 'page_view', 'event_category' => 'page_view', 'path' => '/pricing', 'revenue_minor' => 0, 'occurred_at' => '2026-03-10 10:05:00'],
        ['identity' => $pricingIdentity->id, 'event_name' => 'page_view', 'event_category' => 'page_view', 'path' => '/pricing', 'revenue_minor' => 0, 'occurred_at' => '2026-03-10 10:10:00'],
        ['identity' => $pricingIdentity->id, 'event_name' => 'conversion.completed', 'event_category' => 'conversion', 'path' => '/pricing', 'revenue_minor' => 14900, 'occurred_at' => '2026-03-10 10:15:00'],
        ['identity' => $checkoutIdentity->id, 'event_name' => 'page_view', 'event_category' => 'page_view', 'path' => '/checkout', 'revenue_minor' => 0, 'occurred_at' => '2026-03-10 11:05:00'],
        ['identity' => $checkoutIdentity->id, 'event_name' => 'page_view', 'event_category' => 'page_view', 'path' => '/checkout', 'revenue_minor' => 0, 'occurred_at' => '2026-03-10 11:10:00'],
    ] as $eventData) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $eventData['identity'],
            'occurred_at' => CarbonImmutable::parse($eventData['occurred_at']),
            'event_name' => $eventData['event_name'],
            'event_category' => $eventData['event_category'],
            'path' => $eventData['path'],
            'revenue_minor' => $eventData['revenue_minor'],
        ]);
    }

    $segment = SignalSegment::query()->create([
        'name' => 'Pricing Journey Segment',
        'slug' => 'pricing-journey-segment',
        'conditions' => [
            ['field' => 'path', 'operator' => 'equals', 'value' => '/pricing'],
        ],
    ]);

    $summary = app(ConversionFunnelReportService::class)->summary($property->id, '2026-03-10', '2026-03-10', $segment->id);

    expect($summary['started'])->toBe(1)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['paid'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(14900);
});

it('matches the configured primary outcome without requiring a conversion category', function (): void {
    config()->set('signals.defaults.primary_outcome_event_name', 'registration.completed');

    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Primary Outcome Funnel Owner',
        'email' => 'primary-outcome-funnel-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Primary Outcome Funnel Property',
        'slug' => 'primary-outcome-funnel-property',
        'write_key' => 'primary-outcome-funnel-key',
    ]);

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'primary-outcome-funnel-visitor',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:30:00'),
    ]);

    foreach ([
        ['event_name' => 'page_view', 'event_category' => 'page_view', 'occurred_at' => '2026-03-10 09:00:00'],
        ['event_name' => 'page_view', 'event_category' => 'page_view', 'occurred_at' => '2026-03-10 09:05:00'],
        ['event_name' => 'registration.completed', 'event_category' => 'engagement', 'occurred_at' => '2026-03-10 09:10:00'],
    ] as $eventData) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $identity->id,
            'occurred_at' => CarbonImmutable::parse($eventData['occurred_at']),
            'event_name' => $eventData['event_name'],
            'event_category' => $eventData['event_category'],
            'revenue_minor' => 0,
        ]);
    }

    $summary = app(ConversionFunnelReportService::class)->summary($property->id, '2026-03-10', '2026-03-10');

    expect($summary['started'])->toBe(1)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['paid'])->toBe(1)
        ->and($summary['paid_label'])->toBe('Completed Outcome');
});

it('uses saved funnel definitions for custom conversion funnel reports', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Saved Funnel Owner',
        'email' => 'saved-funnel-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Saved Funnel Property',
        'slug' => 'saved-funnel-property',
        'write_key' => 'saved-funnel-key',
    ]);

    $identityOne = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'saved-funnel-visitor-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:30:00'),
    ]);

    $identityTwo = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'saved-funnel-visitor-2',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:30:00'),
    ]);

    foreach ([
        ['identity' => $identityOne->id, 'event_name' => 'landing_viewed', 'event_category' => 'page_view', 'occurred_at' => '2026-03-10 09:00:00', 'revenue_minor' => 0],
        ['identity' => $identityOne->id, 'event_name' => 'signup_started', 'event_category' => 'engagement', 'occurred_at' => '2026-03-10 09:05:00', 'revenue_minor' => 0],
        ['identity' => $identityOne->id, 'event_name' => 'subscription_activated', 'event_category' => 'conversion', 'occurred_at' => '2026-03-10 09:15:00', 'revenue_minor' => 7900],
        ['identity' => $identityTwo->id, 'event_name' => 'landing_viewed', 'event_category' => 'page_view', 'occurred_at' => '2026-03-10 10:00:00', 'revenue_minor' => 0],
        ['identity' => $identityTwo->id, 'event_name' => 'signup_started', 'event_category' => 'engagement', 'occurred_at' => '2026-03-10 10:05:00', 'revenue_minor' => 0],
    ] as $eventData) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $eventData['identity'],
            'occurred_at' => CarbonImmutable::parse($eventData['occurred_at']),
            'event_name' => $eventData['event_name'],
            'event_category' => $eventData['event_category'],
            'revenue_minor' => $eventData['revenue_minor'],
        ]);
    }

    $savedReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Signup Activation Funnel',
        'slug' => 'signup-activation-funnel',
        'report_type' => 'conversion_funnel',
        'settings' => [
            'funnel_steps' => [
                [
                    'label' => 'Landing Viewed',
                    'event_name' => 'landing_viewed',
                    'event_category' => 'page_view',
                ],
                [
                    'label' => 'Signup Started',
                    'event_name' => 'signup_started',
                    'event_category' => 'engagement',
                ],
                [
                    'label' => 'Subscription Activated',
                    'event_name' => 'subscription_activated',
                    'event_category' => 'conversion',
                ],
            ],
        ],
    ]);

    $service = app(ConversionFunnelReportService::class);
    $summary = $service->summary(null, '2026-03-10', '2026-03-10', null, $savedReport->id);
    $stages = $service->stages(null, '2026-03-10', '2026-03-10', null, $savedReport->id);

    expect($summary['started'])->toBe(2)
        ->and($summary['completed'])->toBe(2)
        ->and($summary['paid'])->toBe(1)
        ->and($summary['started_label'])->toBe('Landing Viewed')
        ->and($summary['completed_label'])->toBe('Signup Started')
        ->and($summary['paid_label'])->toBe('Subscription Activated')
        ->and($summary['overall_rate'])->toBe(50.0)
        ->and($summary['revenue_minor'])->toBe(7900)
        ->and($stages)->toHaveCount(3)
        ->and($stages[0]['label'])->toBe('Landing Viewed')
        ->and($stages[1]['label'])->toBe('Signup Started')
        ->and($stages[2]['label'])->toBe('Subscription Activated')
        ->and($stages[2]['revenue_minor'])->toBe(7900)
        ->and($service->getSavedReportOptions())
        ->toBe([$savedReport->id => 'Signup Activation Funnel']);
});

it('requires sequential funnel progression and honors step windows', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Sequential Funnel Owner',
        'email' => 'sequential-funnel-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Sequential Funnel Property',
        'slug' => 'sequential-funnel-property',
        'write_key' => 'sequential-funnel-key',
    ]);

    $identitySequential = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'sequential-visitor',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:20:00'),
    ]);

    $identitySkipped = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'skipped-visitor',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:30:00'),
    ]);

    $identityExpired = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'expired-visitor',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 11:30:00'),
    ]);

    foreach ([
        ['identity' => $identitySequential->id, 'event_name' => 'landing_viewed', 'event_category' => 'page_view', 'occurred_at' => '2026-03-10 09:00:00', 'revenue_minor' => 0],
        ['identity' => $identitySequential->id, 'event_name' => 'signup_started', 'event_category' => 'engagement', 'occurred_at' => '2026-03-10 09:05:00', 'revenue_minor' => 0],
        ['identity' => $identitySequential->id, 'event_name' => 'subscription_activated', 'event_category' => 'conversion', 'occurred_at' => '2026-03-10 09:10:00', 'revenue_minor' => 5400],
        ['identity' => $identitySkipped->id, 'event_name' => 'subscription_activated', 'event_category' => 'conversion', 'occurred_at' => '2026-03-10 09:15:00', 'revenue_minor' => 8800],
        ['identity' => $identityExpired->id, 'event_name' => 'landing_viewed', 'event_category' => 'page_view', 'occurred_at' => '2026-03-10 10:00:00', 'revenue_minor' => 0],
        ['identity' => $identityExpired->id, 'event_name' => 'signup_started', 'event_category' => 'engagement', 'occurred_at' => '2026-03-10 11:10:00', 'revenue_minor' => 0],
        ['identity' => $identityExpired->id, 'event_name' => 'subscription_activated', 'event_category' => 'conversion', 'occurred_at' => '2026-03-10 11:15:00', 'revenue_minor' => 9900],
    ] as $eventData) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $eventData['identity'],
            'occurred_at' => CarbonImmutable::parse($eventData['occurred_at']),
            'event_name' => $eventData['event_name'],
            'event_category' => $eventData['event_category'],
            'revenue_minor' => $eventData['revenue_minor'],
        ]);
    }

    $savedReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Windowed Sequential Funnel',
        'slug' => 'windowed-sequential-funnel',
        'report_type' => 'conversion_funnel',
        'settings' => [
            'funnel_steps' => [
                [
                    'label' => 'Landing Viewed',
                    'event_name' => 'landing_viewed',
                    'event_category' => 'page_view',
                ],
                [
                    'label' => 'Signup Started',
                    'event_name' => 'signup_started',
                    'event_category' => 'engagement',
                ],
                [
                    'label' => 'Subscription Activated',
                    'event_name' => 'subscription_activated',
                    'event_category' => 'conversion',
                ],
            ],
            'step_window_minutes' => 30,
        ],
    ]);

    $service = app(ConversionFunnelReportService::class);
    $summary = $service->summary(savedReportId: $savedReport->id, from: '2026-03-10', until: '2026-03-10');
    $stages = $service->stages(savedReportId: $savedReport->id, from: '2026-03-10', until: '2026-03-10');

    expect($summary['started'])->toBe(2)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['paid'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(5400)
        ->and($summary['overall_rate'])->toBe(50.0)
        ->and($stages[0]['count'])->toBe(2)
        ->and($stages[1]['count'])->toBe(1)
        ->and($stages[2]['count'])->toBe(1)
        ->and($stages[2]['revenue_minor'])->toBe(5400);
});
