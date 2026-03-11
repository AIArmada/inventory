<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\AcquisitionReportService;
use AIArmada\Signals\Services\SavedSignalReportDefinition;
use Carbon\CarbonImmutable;

uses(SignalsTestCase::class);

it('builds an owner-safe acquisition report', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Acquisition Owner A',
        'email' => 'acquisition-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Acquisition Owner B',
        'email' => 'acquisition-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Owner A Acquisition Property',
        'slug' => 'owner-a-acquisition-property',
        'write_key' => 'owner-a-acquisition-key',
    ]);
    $propertyA->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Owner B Acquisition Property',
        'slug' => 'owner-b-acquisition-property',
        'write_key' => 'owner-b-acquisition-key',
    ]);
    $propertyB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $identityA1 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'acquisition-a-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ]);
    $identityA1->assignOwner($ownerA)->save();

    $identityA2 = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'acquisition-a-2',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 10:00:00'),
    ]);
    $identityA2->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'acquisition-b-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 11:00:00'),
    ]);
    $identityB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $pageViewA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'spring-sale',
        'content' => 'hero-banner',
        'term' => 'running shoes',
        'referrer' => 'https://google.com',
    ]);
    $pageViewA1->assignOwner($ownerA)->save();

    $conversionA1 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA1->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:15:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'spring-sale',
        'content' => 'hero-banner',
        'term' => 'running shoes',
        'referrer' => 'https://google.com',
        'revenue_minor' => 15000,
    ]);
    $conversionA1->assignOwner($ownerA)->save();

    $directPageViewA2 = SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA2->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 10:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'content' => 'direct-nav',
    ]);
    $directPageViewA2->assignOwner($ownerA)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $pageViewB = SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 11:05:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'launch',
        'content' => 'launch-email',
        'term' => 'vip list',
    ]);
    $pageViewB->assignOwner($ownerB)->save();

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $service = app(AcquisitionReportService::class);
    $summary = $service->summary($propertyA->id, '2026-03-10', '2026-03-10');
    $row = $service->getTableQuery($propertyA->id, '2026-03-10', '2026-03-10')
        ->where('source', 'google')
        ->first();

    expect($summary['attributed_events'])->toBe(3)
        ->and($summary['visitors'])->toBe(2)
        ->and($summary['conversions'])->toBe(1)
        ->and($summary['revenue_minor'])->toBe(15000)
        ->and($summary['campaigns'])->toBe(1)
        ->and($row)->not()->toBeNull()
        ->and($row?->tracked_property_id)->toBe($propertyA->id)
        ->and($row?->acquisition_source)->toBe('google')
        ->and($row?->acquisition_medium)->toBe('cpc')
        ->and($row?->acquisition_campaign)->toBe('spring-sale')
        ->and($row?->acquisition_content)->toBe('hero-banner')
        ->and($row?->acquisition_term)->toBe('running shoes')
        ->and((int) ($row?->events ?? 0))->toBe(2)
        ->and((int) ($row?->visitors ?? 0))->toBe(1)
        ->and((int) ($row?->conversions ?? 0))->toBe(1)
        ->and((int) ($row?->revenue_minor ?? 0))->toBe(15000)
        ->and($service->getTrackedPropertyOptions())
        ->toBe([$propertyA->id => 'Owner A Acquisition Property']);
});

it('supports first touch and last touch acquisition attribution from saved reports', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Attribution Owner',
        'email' => 'attribution-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Attribution Property',
        'slug' => 'attribution-property',
        'write_key' => 'attribution-key',
    ]);

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'attribution-visitor-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:30:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'launch',
        'content' => 'hero',
        'term' => 'analytics',
        'referrer' => 'https://google.com',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:10:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'nurture',
        'content' => 'cta',
        'term' => 'vip',
        'referrer' => 'https://mail.example.test',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:15:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'nurture',
        'content' => 'cta',
        'term' => 'vip',
        'referrer' => 'https://mail.example.test',
        'revenue_minor' => 4200,
    ]);

    $firstTouchReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'First Touch Acquisition',
        'slug' => 'first-touch-acquisition',
        'report_type' => 'acquisition',
        'settings' => [
            'attribution_model' => SavedSignalReportDefinition::ATTRIBUTION_MODEL_FIRST_TOUCH,
            'conversion_event_name' => 'order.paid',
        ],
    ]);

    $lastTouchReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Last Touch Acquisition',
        'slug' => 'last-touch-acquisition',
        'report_type' => 'acquisition',
        'settings' => [
            'attribution_model' => SavedSignalReportDefinition::ATTRIBUTION_MODEL_LAST_TOUCH,
            'conversion_event_name' => 'order.paid',
        ],
    ]);

    $service = app(AcquisitionReportService::class);

    $firstTouchSummary = $service->summary(savedReportId: $firstTouchReport->id, from: '2026-03-10', until: '2026-03-10');
    $firstTouchRow = $service->getTableQuery(savedReportId: $firstTouchReport->id, from: '2026-03-10', until: '2026-03-10')->first();

    $lastTouchSummary = $service->summary(savedReportId: $lastTouchReport->id, from: '2026-03-10', until: '2026-03-10');
    $lastTouchRow = $service->getTableQuery(savedReportId: $lastTouchReport->id, from: '2026-03-10', until: '2026-03-10')->first();

    expect($firstTouchSummary['attributed_events'])->toBe(1)
        ->and($firstTouchSummary['conversions'])->toBe(1)
        ->and($firstTouchSummary['campaigns'])->toBe(1)
        ->and($firstTouchSummary['revenue_minor'])->toBe(4200)
        ->and($firstTouchRow)->not()->toBeNull()
        ->and($firstTouchRow?->acquisition_source)->toBe('google')
        ->and($firstTouchRow?->acquisition_medium)->toBe('cpc')
        ->and($firstTouchRow?->acquisition_campaign)->toBe('launch')
        ->and($lastTouchSummary['attributed_events'])->toBe(1)
        ->and($lastTouchSummary['conversions'])->toBe(1)
        ->and($lastTouchSummary['campaigns'])->toBe(1)
        ->and($lastTouchSummary['revenue_minor'])->toBe(4200)
        ->and($lastTouchRow)->not()->toBeNull()
        ->and($lastTouchRow?->acquisition_source)->toBe('newsletter')
        ->and($lastTouchRow?->acquisition_medium)->toBe('email')
        ->and($lastTouchRow?->acquisition_campaign)->toBe('nurture')
        ->and($service->getSavedReportOptions())
        ->toBe([
            $firstTouchReport->id => 'First Touch Acquisition',
            $lastTouchReport->id => 'Last Touch Acquisition',
        ]);
});

it('uses the configured primary outcome event when acquisition settings omit one', function (): void {
    config()->set('signals.defaults.primary_outcome_event_name', 'registration.completed');

    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Primary Outcome Owner',
        'email' => 'primary-outcome-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Primary Outcome Property',
        'slug' => 'primary-outcome-property',
        'write_key' => 'primary-outcome-key',
    ]);

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'primary-outcome-visitor',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:30:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
        'source' => 'community',
        'medium' => 'referral',
        'campaign' => 'launch',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:15:00'),
        'event_name' => 'registration.completed',
        'event_category' => 'conversion',
        'source' => 'community',
        'medium' => 'referral',
        'campaign' => 'launch',
    ]);

    $savedReport = SavedSignalReport::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Primary Outcome Acquisition',
        'slug' => 'primary-outcome-acquisition',
        'report_type' => 'acquisition',
        'settings' => [
            'attribution_model' => SavedSignalReportDefinition::ATTRIBUTION_MODEL_FIRST_TOUCH,
        ],
    ]);

    $summary = app(AcquisitionReportService::class)->summary(
        savedReportId: $savedReport->id,
        from: '2026-03-10',
        until: '2026-03-10',
    );

    expect($summary['attributed_events'])->toBe(1)
        ->and($summary['conversions'])->toBe(1)
        ->and($summary['campaigns'])->toBe(1);
});
