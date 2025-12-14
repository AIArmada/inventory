<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Services\ProgramService;

beforeEach(function (): void {
    $this->service = app(ProgramService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'PROG-' . uniqid(),
        'name' => 'Program Test Affiliate',
        'contact_email' => 'prog@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->program = AffiliateProgram::create([
        'name' => 'Test Program',
        'slug' => 'test-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'is_public' => true,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
    ]);
});

describe('ProgramService', function (): void {
    describe('getAvailablePrograms', function (): void {
        test('returns active public programs', function (): void {
            $result = $this->service->getAvailablePrograms();

            expect($result)->toBeCollection();
            expect($result->pluck('id'))->toContain($this->program->id);
        });

        test('excludes inactive programs', function (): void {
            $inactiveProgram = AffiliateProgram::create([
                'name' => 'Inactive Program',
                'slug' => 'inactive-program-' . uniqid(),
                'status' => ProgramStatus::Draft,
                'is_public' => true,
            ]);

            $result = $this->service->getAvailablePrograms();

            expect($result->pluck('id'))->not->toContain($inactiveProgram->id);
        });

        test('excludes private programs', function (): void {
            $privateProgram = AffiliateProgram::create([
                'name' => 'Private Program',
                'slug' => 'private-program-' . uniqid(),
                'status' => ProgramStatus::Active,
                'is_public' => false,
            ]);

            $result = $this->service->getAvailablePrograms();

            expect($result->pluck('id'))->not->toContain($privateProgram->id);
        });
    });

    describe('joinProgram', function (): void {
        test('creates membership with approved status when no approval required', function (): void {
            $membership = $this->service->joinProgram($this->affiliate, $this->program);

            expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
            expect($membership->status)->toBe(MembershipStatus::Approved);
            expect($membership->approved_at)->not->toBeNull();
        });

        test('creates membership with pending status when approval required', function (): void {
            $this->program->update(['requires_approval' => true]);

            $membership = $this->service->joinProgram($this->affiliate, $this->program);

            expect($membership->status)->toBe(MembershipStatus::Pending);
            expect($membership->approved_at)->toBeNull();
        });

        test('returns existing membership if already joined', function (): void {
            $first = $this->service->joinProgram($this->affiliate, $this->program);
            $second = $this->service->joinProgram($this->affiliate, $this->program);

            expect($first->id)->toBe($second->id);
        });

        test('sets applied_at timestamp', function (): void {
            $membership = $this->service->joinProgram($this->affiliate, $this->program);

            expect($membership->applied_at)->not->toBeNull();
        });
    });

    describe('leaveProgram', function (): void {
        test('removes membership when exists', function (): void {
            $this->service->joinProgram($this->affiliate, $this->program);
            $this->service->leaveProgram($this->affiliate, $this->program);

            expect(AffiliateProgramMembership::where([
                'affiliate_id' => $this->affiliate->id,
                'program_id' => $this->program->id,
            ])->exists())->toBeFalse();
        });

        test('does not throw if not a member', function (): void {
            // Should complete without exception
            $this->service->leaveProgram($this->affiliate, $this->program);
            expect(true)->toBeTrue();
        });
    });

    describe('approveMembership', function (): void {
        test('approves pending membership', function (): void {
            $this->program->update(['requires_approval' => true]);

            $membership = $this->service->joinProgram($this->affiliate, $this->program);
            expect($membership->status)->toBe(MembershipStatus::Pending);

            $this->service->approveMembership($membership, 'admin@example.com');

            $membership->refresh();
            expect($membership->status)->toBe(MembershipStatus::Approved);
            expect($membership->approved_at)->not->toBeNull();
        });
    });

    describe('upgradeTier', function (): void {
        test('throws exception if not a member', function (): void {
            $tier = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Gold',
                'level' => 3,
                'commission_rate_basis_points' => 2000,
            ]);

            $this->service->upgradeTier($this->affiliate, $this->program, $tier);
        })->throws(RuntimeException::class, 'Affiliate is not a member of this program');

        test('upgrades affiliate to new tier', function (): void {
            $tier1 = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Bronze',
                'level' => 1,
                'commission_rate_basis_points' => 1000,
            ]);

            $tier2 = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Silver',
                'level' => 2,
                'commission_rate_basis_points' => 1500,
            ]);

            $membership = $this->service->joinProgram($this->affiliate, $this->program);
            $membership->update(['tier_id' => $tier1->id]);

            $result = $this->service->upgradeTier($this->affiliate, $this->program, $tier2);

            expect($result->tier_id)->toBe($tier2->id);
        });
    });

    describe('isMember', function (): void {
        test('returns true when approved member', function (): void {
            $this->service->joinProgram($this->affiliate, $this->program);

            $result = $this->service->isMember($this->affiliate, $this->program);

            expect($result)->toBeTrue();
        });

        test('returns false when not a member', function (): void {
            $result = $this->service->isMember($this->affiliate, $this->program);

            expect($result)->toBeFalse();
        });

        test('returns false when pending member', function (): void {
            $this->program->update(['requires_approval' => true]);
            $this->service->joinProgram($this->affiliate, $this->program);

            $result = $this->service->isMember($this->affiliate, $this->program);

            expect($result)->toBeFalse();
        });
    });

    describe('getMembership', function (): void {
        test('returns membership when exists', function (): void {
            $this->service->joinProgram($this->affiliate, $this->program);

            $result = $this->service->getMembership($this->affiliate, $this->program);

            expect($result)->toBeInstanceOf(AffiliateProgramMembership::class);
        });

        test('returns null when not a member', function (): void {
            $result = $this->service->getMembership($this->affiliate, $this->program);

            expect($result)->toBeNull();
        });

        test('loads tier relationship', function (): void {
            $tier = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Bronze',
                'level' => 1,
                'commission_rate_basis_points' => 1000,
            ]);

            $membership = $this->service->joinProgram($this->affiliate, $this->program);
            $membership->update(['tier_id' => $tier->id]);

            $result = $this->service->getMembership($this->affiliate, $this->program);

            expect($result->relationLoaded('tier'))->toBeTrue();
        });
    });

    describe('processTierUpgrades', function (): void {
        test('returns count of upgraded memberships', function (): void {
            $this->service->joinProgram($this->affiliate, $this->program);

            $result = $this->service->processTierUpgrades($this->program);

            expect($result)->toBeInt();
            expect($result)->toBeGreaterThanOrEqual(0);
        });

        test('only processes approved memberships', function (): void {
            $this->program->update(['requires_approval' => true]);
            $this->service->joinProgram($this->affiliate, $this->program);

            $result = $this->service->processTierUpgrades($this->program);

            expect($result)->toBe(0);
        });
    });

    describe('getAffiliatePrograms', function (): void {
        test('returns collection', function (): void {
            $result = $this->service->getAffiliatePrograms($this->affiliate);

            expect($result)->toBeCollection();
        });
    });
});

describe('ProgramService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = app(ProgramService::class);
        expect($service)->toBeInstanceOf(ProgramService::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(ProgramService::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(ProgramService::class);

        expect($reflection->hasMethod('getAvailablePrograms'))->toBeTrue();
        expect($reflection->hasMethod('joinProgram'))->toBeTrue();
        expect($reflection->hasMethod('leaveProgram'))->toBeTrue();
        expect($reflection->hasMethod('approveMembership'))->toBeTrue();
        expect($reflection->hasMethod('upgradeTier'))->toBeTrue();
        expect($reflection->hasMethod('isMember'))->toBeTrue();
        expect($reflection->hasMethod('getMembership'))->toBeTrue();
    });
});
