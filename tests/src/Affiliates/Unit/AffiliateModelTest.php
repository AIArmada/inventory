<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateCreated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateRank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Event;

describe('Affiliate Model - Owner Scopes', function (): void {
    test('scopeForOwner filters by owner when enabled', function (): void {
        config(['affiliates.owner.enabled' => true]);

        $owner1 = new class extends Model
        {
            public function getMorphClass()
            {
                return 'User';
            }

            public function getKey()
            {
                return 1;
            }
        };

        $affiliate1 = Affiliate::create([
            'code' => 'AFF1',
            'name' => 'Test Affiliate 1',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
            'owner_type' => 'User',
            'owner_id' => 1,
        ]);

        $affiliate2 = Affiliate::create([
            'code' => 'AFF2',
            'name' => 'Test Affiliate 2',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
            'owner_type' => 'User',
            'owner_id' => 2,
        ]);

        $results = Affiliate::forOwner($owner1)->pluck('id');

        expect($results)->toContain($affiliate1->id);
        expect($results)->not->toContain($affiliate2->id);
    });

    test('scopeForOwner returns all when disabled', function (): void {
        config(['affiliates.owner.enabled' => false]);

        $affiliate1 = Affiliate::create([
            'code' => 'AFF1',
            'name' => 'Test Affiliate 1',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $affiliate2 = Affiliate::create([
            'code' => 'AFF2',
            'name' => 'Test Affiliate 2',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $results = Affiliate::forOwner()->pluck('id');

        expect($results)->toContain($affiliate1->id);
        expect($results)->toContain($affiliate2->id);
    });
});

describe('Affiliate Model - Status Methods', function (): void {
    test('isActive returns true for active status', function (): void {
        $affiliate = new Affiliate(['status' => AffiliateStatus::Active]);
        expect($affiliate->isActive())->toBeTrue();
    });

    test('isActive returns false for non-active status', function (): void {
        $affiliate = new Affiliate(['status' => AffiliateStatus::Pending]);
        expect($affiliate->isActive())->toBeFalse();
    });

    test('isActive returns false for paused status', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAUSED-001',
            'name' => 'Paused Affiliate',
            'status' => AffiliateStatus::Paused,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->isActive())->toBeFalse();
    });

    test('isActive returns false for disabled status', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'DISABLED-001',
            'name' => 'Disabled Affiliate',
            'status' => AffiliateStatus::Disabled,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->isActive())->toBeFalse();
    });
});

describe('Affiliate Model - Relationships', function (): void {
    test('has attributions relationship', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'AFF1',
            'name' => 'Test Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
        expect($affiliate->attributions())->toBeInstanceOf(HasMany::class);
    });

    test('has conversions relationship', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'AFF1',
            'name' => 'Test Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
        expect($affiliate->conversions())->toBeInstanceOf(HasMany::class);
    });

    test('has parent relationship', function (): void {
        $affiliate = new Affiliate;
        expect($affiliate->parent())->toBeInstanceOf(BelongsTo::class);
    });

    test('has children relationship', function (): void {
        $affiliate = new Affiliate;
        expect($affiliate->children())->toBeInstanceOf(HasMany::class);
    });

    test('has many links', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'LINK-001',
            'name' => 'Link Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->links())->toBeInstanceOf(HasMany::class);
    });

    test('has many payouts', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAY-001',
            'name' => 'Payout Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->payouts())->toBeInstanceOf(MorphMany::class);
    });

    test('has many fraud signals', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-001',
            'name' => 'Fraud Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->fraudSignals())->toBeInstanceOf(HasMany::class);
    });

    test('has many daily stats', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STATS-001',
            'name' => 'Stats Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->dailyStats())->toBeInstanceOf(HasMany::class);
    });

    test('has one balance', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'BAL-001',
            'name' => 'Balance Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->balance())->toBeInstanceOf(HasOne::class);
    });

    test('has many payout methods', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PM-001',
            'name' => 'Payout Method Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->payoutMethods())->toBeInstanceOf(HasMany::class);
    });

    test('has many payout holds', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-001',
            'name' => 'Hold Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
        expect($affiliate->payoutHolds())->toBeInstanceOf(HasMany::class);
    });

    test('belongs to rank', function (): void {
        $affiliate = new Affiliate;
        expect($affiliate->rank())->toBeInstanceOf(BelongsTo::class);
    });

    test('parent-child relationship works correctly', function (): void {
        $parent = Affiliate::create([
            'code' => 'PARENT-001',
            'name' => 'Parent Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $child = Affiliate::create([
            'code' => 'CHILD-001',
            'name' => 'Child Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $parent->id,
        ]);

        expect($child->parent->id)->toBe($parent->id);
        expect($parent->children)->toHaveCount(1);
        expect($parent->children->first()->id)->toBe($child->id);
    });
});

describe('Affiliate Model - Payout Hold Methods', function (): void {
    test('hasActivePayoutHold returns true with unreleased hold', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLDCHECK-001',
            'name' => 'Hold Check Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'Under review',
            'released_at' => null,
        ]);

        expect($affiliate->hasActivePayoutHold())->toBeTrue();
    });

    test('hasActivePayoutHold returns false when hold is released', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RELEASE-001',
            'name' => 'Release Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'Was under review',
            'released_at' => now(),
        ]);

        expect($affiliate->hasActivePayoutHold())->toBeFalse();
    });

    test('hasActivePayoutHold returns false when hold is expired', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EXPIRE-001',
            'name' => 'Expire Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'Temporary hold',
            'expires_at' => now()->subDay(),
        ]);

        expect($affiliate->hasActivePayoutHold())->toBeFalse();
    });

    test('hasActivePayoutHold returns false with no holds', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'NOHOLD-001',
            'name' => 'No Hold Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->hasActivePayoutHold())->toBeFalse();
    });
});

describe('Affiliate Model - Payout Request Methods', function (): void {
    test('canRequestPayout returns false when not active', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAYOUT-INACTIVE',
            'name' => 'Payout Inactive Test',
            'status' => AffiliateStatus::Paused,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->canRequestPayout())->toBeFalse();
    });

    test('canRequestPayout returns false with active hold', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAYOUT-HOLD',
            'name' => 'Payout Hold Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'Hold active',
        ]);

        expect($affiliate->canRequestPayout())->toBeFalse();
    });

    test('canRequestPayout returns false without balance', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAYOUT-NOBAL',
            'name' => 'Payout No Balance Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->canRequestPayout())->toBeFalse();
    });

    test('canRequestPayout returns false with zero available balance', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAYOUT-ZEROBAL',
            'name' => 'Zero Balance Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateBalance::create([
            'affiliate_id' => $affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 0,
            'lifetime_earnings_minor' => 0,
            'minimum_payout_minor' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->canRequestPayout())->toBeFalse();
    });

    test('canRequestPayout returns true when all conditions met', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PAYOUT-OK',
            'name' => 'Payout OK Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateBalance::create([
            'affiliate_id' => $affiliate->id,
            'available_minor' => 10000,
            'holding_minor' => 0,
            'lifetime_earnings_minor' => 10000,
            'minimum_payout_minor' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->canRequestPayout())->toBeTrue();
    });
});

describe('Affiliate Model - Accessors', function (): void {
    test('email accessor returns contact_email', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EMAIL-001',
            'name' => 'Email Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
            'contact_email' => 'test@example.com',
        ]);

        expect($affiliate->email)->toBe('test@example.com');
    });

    test('commissionRateBasisPoints accessor returns commission_rate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RATE-001',
            'name' => 'Rate Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1500,
            'currency' => 'USD',
        ]);

        expect($affiliate->commission_rate_basis_points)->toBe(1500);
    });
});

describe('Affiliate Model - Casts', function (): void {
    test('casts status as enum', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'CAST-001',
            'name' => 'Cast Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->status)->toBeInstanceOf(AffiliateStatus::class);
    });

    test('casts commission_type as enum', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'TYPE-001',
            'name' => 'Type Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($affiliate->commission_type)->toBeInstanceOf(CommissionType::class);
    });

    test('casts metadata as array', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'META-001',
            'name' => 'Metadata Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
            'metadata' => ['key' => 'value'],
        ]);

        expect($affiliate->metadata)->toBeArray()
            ->and($affiliate->metadata['key'])->toBe('value');
    });

    test('casts activated_at as datetime', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'ACTIVATED-001',
            'name' => 'Activated Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
            'activated_at' => now(),
        ]);

        expect($affiliate->activated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
});

describe('Affiliate Model - Events', function (): void {
    test('dispatches AffiliateCreated event on creation', function (): void {
        Event::fake([AffiliateCreated::class]);

        Affiliate::create([
            'code' => 'EVENT-001',
            'name' => 'Event Test Affiliate',
            'status' => AffiliateStatus::Draft,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        Event::assertDispatched(AffiliateCreated::class);
    });

    test('dispatches AffiliateActivated event when status changes to Active', function (): void {
        Event::fake([AffiliateActivated::class, AffiliateCreated::class]);

        $affiliate = Affiliate::create([
            'code' => 'ACTIVATE-001',
            'name' => 'Activation Test',
            'status' => AffiliateStatus::Draft,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $affiliate->update(['status' => AffiliateStatus::Active]);

        Event::assertDispatched(AffiliateActivated::class);
    });

    test('does not dispatch AffiliateActivated when status stays same', function (): void {
        Event::fake([AffiliateActivated::class, AffiliateCreated::class]);

        $affiliate = Affiliate::create([
            'code' => 'SAME-001',
            'name' => 'Same Status Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        Event::assertNotDispatched(AffiliateActivated::class);

        $affiliate->update(['name' => 'Updated Name']);

        Event::assertNotDispatched(AffiliateActivated::class);
    });
});

describe('Affiliate Model - Cascade Deletes', function (): void {
    test('cascade deletes attributions on delete', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'CASCADE-001',
            'name' => 'Cascade Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateAttribution::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'cart_instance' => 'default',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
        ]);

        $affiliate->delete();

        expect(AffiliateAttribution::where('affiliate_id', $affiliate->id)->count())->toBe(0);
    });

    test('cascade deletes conversions on delete', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'CASCADE-002',
            'name' => 'Cascade Conversion Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'order_reference' => 'ORDER-CASCADE',
            'total_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
        ]);

        $affiliate->delete();

        expect(AffiliateConversion::where('affiliate_id', $affiliate->id)->count())->toBe(0);
    });

    test('nulls parent_affiliate_id of children on delete', function (): void {
        $parent = Affiliate::create([
            'code' => 'PARENT-CASCADE',
            'name' => 'Parent Cascade',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $child = Affiliate::create([
            'code' => 'CHILD-CASCADE',
            'name' => 'Child Cascade',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $parent->id,
        ]);

        $parent->delete();
        $child->refresh();

        expect($child->parent_affiliate_id)->toBeNull();
    });
});

describe('Affiliate Model - Table Configuration', function (): void {
    test('uses correct table name from config', function (): void {
        $affiliate = new Affiliate;
        expect($affiliate->getTable())->toBe('affiliate_affiliates');
    });
});
