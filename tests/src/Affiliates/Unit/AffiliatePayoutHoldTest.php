<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;

describe('AffiliatePayoutHold Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'HOLD' . uniqid(),
            'name' => 'Hold Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'fraud_investigation',
            'notes' => 'Suspected fraudulent activity',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold)->toBeInstanceOf(AffiliatePayoutHold::class);
        expect($hold->reason)->toBe('fraud_investigation');
        expect($hold->notes)->toBe('Suspected fraudulent activity');
        expect($hold->placed_by)->toBe('admin@example.com');
    });

    test('belongs to affiliate', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'verification_pending',
            'placed_by' => 'system',
        ]);

        expect($hold->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($hold->affiliate->id)->toBe($this->affiliate->id);
    });

    test('isActive returns true when not released and not expired', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'manual_review',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold->isActive())->toBeTrue();
    });

    test('isActive returns false when released', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'manual_review',
            'placed_by' => 'admin@example.com',
            'released_at' => now(),
        ]);

        expect($hold->isActive())->toBeFalse();
    });

    test('isActive returns false when expired', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'temporary_hold',
            'placed_by' => 'admin@example.com',
            'expires_at' => now()->subDay(),
        ]);

        expect($hold->isActive())->toBeFalse();
    });

    test('isActive returns true when not yet expired', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'temporary_hold',
            'placed_by' => 'admin@example.com',
            'expires_at' => now()->addWeek(),
        ]);

        expect($hold->isActive())->toBeTrue();
    });

    test('release method sets released_at timestamp', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'verification_pending',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold->released_at)->toBeNull();

        $hold->release();

        $hold->refresh();
        expect($hold->released_at)->not->toBeNull();
        expect($hold->isActive())->toBeFalse();
    });

    test('casts expires_at as datetime', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'expiring_hold',
            'placed_by' => 'admin@example.com',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        expect($hold->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($hold->expires_at->format('Y-m-d'))->toBe('2024-12-31');
    });

    test('casts released_at as datetime', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'released_hold',
            'placed_by' => 'admin@example.com',
            'released_at' => '2024-12-15 10:00:00',
        ]);

        expect($hold->released_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($hold->released_at->format('Y-m-d'))->toBe('2024-12-15');
    });

    test('can store optional notes', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'manual_review',
            'notes' => 'Detailed investigation notes here',
            'placed_by' => 'compliance@example.com',
        ]);

        expect($hold->notes)->toBe('Detailed investigation notes here');
    });

    test('notes can be null', function (): void {
        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $this->affiliate->id,
            'reason' => 'quick_hold',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold->notes)->toBeNull();
    });

    test('uses correct table name from config', function (): void {
        $hold = new AffiliatePayoutHold;

        expect($hold->getTable())->toBe(config('affiliates.table_names.payout_holds', 'affiliate_payout_holds'));
    });
});
