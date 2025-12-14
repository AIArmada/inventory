<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateVolumeTier Model', function (): void {
    it('can be created with required fields', function (): void {
        $tier = AffiliateVolumeTier::create([
            'name' => 'Bronze Tier',
            'min_volume_minor' => 0,
            'max_volume_minor' => 10000,
            'commission_rate_basis_points' => 500,
            'period' => 'monthly',
        ]);

        expect($tier)->toBeInstanceOf(AffiliateVolumeTier::class)
            ->and($tier->name)->toBe('Bronze Tier')
            ->and($tier->min_volume_minor)->toBe(0)
            ->and($tier->max_volume_minor)->toBe(10000)
            ->and($tier->commission_rate_basis_points)->toBe(500);
    });

    it('belongs to a program', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-volume-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateVolumeTier::create([
            'program_id' => $program->id,
            'name' => 'Silver Tier',
            'min_volume_minor' => 10001,
            'max_volume_minor' => 50000,
            'commission_rate_basis_points' => 750,
            'period' => 'monthly',
        ]);

        expect($tier->program())->toBeInstanceOf(BelongsTo::class)
            ->and($tier->program->id)->toBe($program->id);
    });

    it('checks if volume is within tier range', function (): void {
        $tier = AffiliateVolumeTier::create([
            'name' => 'Mid Tier',
            'min_volume_minor' => 10000,
            'max_volume_minor' => 50000,
            'commission_rate_basis_points' => 750,
            'period' => 'monthly',
        ]);

        expect($tier->containsVolume(5000))->toBeFalse()
            ->and($tier->containsVolume(10000))->toBeTrue()
            ->and($tier->containsVolume(30000))->toBeTrue()
            ->and($tier->containsVolume(50000))->toBeTrue()
            ->and($tier->containsVolume(50001))->toBeFalse();
    });

    it('handles tier with no maximum', function (): void {
        $tier = AffiliateVolumeTier::create([
            'name' => 'Unlimited Tier',
            'min_volume_minor' => 100000,
            'max_volume_minor' => null,
            'commission_rate_basis_points' => 1500,
            'period' => 'monthly',
        ]);

        expect($tier->containsVolume(99999))->toBeFalse()
            ->and($tier->containsVolume(100000))->toBeTrue()
            ->and($tier->containsVolume(500000))->toBeTrue()
            ->and($tier->containsVolume(1000000))->toBeTrue();
    });

    it('calculates commission rate percentage', function (): void {
        $tier = AffiliateVolumeTier::create([
            'name' => 'Test Tier',
            'min_volume_minor' => 0,
            'max_volume_minor' => 10000,
            'commission_rate_basis_points' => 1250,
            'period' => 'monthly',
        ]);

        expect($tier->getCommissionRatePercentage())->toBe(12.5);
    });

    it('casts numeric fields as integers', function (): void {
        $tier = AffiliateVolumeTier::create([
            'name' => 'Cast Test Tier',
            'min_volume_minor' => '5000',
            'max_volume_minor' => '25000',
            'commission_rate_basis_points' => '600',
            'period' => 'weekly',
        ]);

        expect($tier->min_volume_minor)->toBeInt()
            ->and($tier->max_volume_minor)->toBeInt()
            ->and($tier->commission_rate_basis_points)->toBeInt();
    });

    it('supports different period values', function (): void {
        $monthlyTier = AffiliateVolumeTier::create([
            'name' => 'Monthly Tier',
            'min_volume_minor' => 0,
            'max_volume_minor' => 10000,
            'commission_rate_basis_points' => 500,
            'period' => 'monthly',
        ]);

        $weeklyTier = AffiliateVolumeTier::create([
            'name' => 'Weekly Tier',
            'min_volume_minor' => 0,
            'max_volume_minor' => 5000,
            'commission_rate_basis_points' => 400,
            'period' => 'weekly',
        ]);

        expect($monthlyTier->period)->toBe('monthly')
            ->and($weeklyTier->period)->toBe('weekly');
    });

    it('uses correct table name from config', function (): void {
        $tier = new AffiliateVolumeTier;
        expect($tier->getTable())->toBe('affiliate_volume_tiers');
    });
});
