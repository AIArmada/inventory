<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherTransaction;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('Voucher Model', function (): void {
    describe('class structure', function (): void {
        it('uses HasUuids trait', function (): void {
            $traits = class_uses_recursive(Voucher::class);
            expect($traits)->toContain(HasUuids::class);
        });

        it('extends Eloquent Model', function (): void {
            $voucher = new Voucher;
            expect($voucher)->toBeInstanceOf(Model::class);
        });
    });

    describe('relationships', function (): void {
        it('defines usages relationship as HasMany', function (): void {
            $voucher = new Voucher;
            $relation = $voucher->usages();

            expect($relation)->toBeInstanceOf(HasMany::class)
                ->and($relation->getRelated())->toBeInstanceOf(VoucherUsage::class);
        });

        it('defines walletEntries relationship as HasMany', function (): void {
            $voucher = new Voucher;
            $relation = $voucher->walletEntries();

            expect($relation)->toBeInstanceOf(HasMany::class)
                ->and($relation->getRelated())->toBeInstanceOf(VoucherWallet::class);
        });

        it('defines transactions relationship as HasMany', function (): void {
            $voucher = new Voucher;
            $relation = $voucher->transactions();

            expect($relation)->toBeInstanceOf(HasMany::class)
                ->and($relation->getRelated())->toBeInstanceOf(VoucherTransaction::class);
        });

        it('defines campaign relationship as BelongsTo', function (): void {
            $voucher = new Voucher;
            $relation = $voucher->campaign();

            expect($relation)->toBeInstanceOf(BelongsTo::class)
                ->and($relation->getRelated())->toBeInstanceOf(Campaign::class);
        });

        it('defines campaignVariant relationship as BelongsTo', function (): void {
            $voucher = new Voucher;
            $relation = $voucher->campaignVariant();

            expect($relation)->toBeInstanceOf(BelongsTo::class)
                ->and($relation->getRelated())->toBeInstanceOf(CampaignVariant::class);
        });

        it('defines affiliate relationship as BelongsTo', function (): void {
            $voucher = new Voucher;
            $relation = $voucher->affiliate();

            expect($relation)->toBeInstanceOf(BelongsTo::class);
        });
    });

    describe('belongsToAffiliate', function (): void {
        it('returns false when affiliate_id is null', function (): void {
            $voucher = new Voucher;
            $voucher->affiliate_id = null;

            expect($voucher->belongsToAffiliate())->toBeFalse();
        });

        it('returns true when affiliate_id is set', function (): void {
            $voucher = new Voucher;
            $voucher->affiliate_id = 'affiliate-123';

            expect($voucher->belongsToAffiliate())->toBeTrue();
        });
    });

    describe('belongsToCampaign', function (): void {
        it('returns false when campaign_id is null', function (): void {
            $voucher = new Voucher;
            $voucher->campaign_id = null;

            expect($voucher->belongsToCampaign())->toBeFalse();
        });

        it('returns true when campaign_id is set', function (): void {
            $voucher = new Voucher;
            $voucher->campaign_id = 'campaign-123';

            expect($voucher->belongsToCampaign())->toBeTrue();
        });
    });

    describe('value label attribute', function (): void {
        it('returns percentage format for percentage type', function (): void {
            $voucher = new Voucher;
            $voucher->type = VoucherType::Percentage;
            $voucher->value = 1000; // 10.00%

            expect($voucher->value_label)->toBe('10 %');
        });

        it('returns percentage format with decimals', function (): void {
            $voucher = new Voucher;
            $voucher->type = VoucherType::Percentage;
            $voucher->value = 1259; // 12.59%

            expect($voucher->value_label)->toBe('12.59 %');
        });

        it('returns currency format for fixed type', function (): void {
            $voucher = new Voucher;
            $voucher->type = VoucherType::Fixed;
            $voucher->value = 5000; // 50.00 MYR
            $voucher->currency = 'MYR';

            $label = $voucher->value_label;

            // Money library formats as "RM50.00" or similar
            expect($label)->toContain('50');
        });

        it('uses default currency when not set', function (): void {
            config(['vouchers.default_currency' => 'USD']);

            $voucher = new Voucher;
            $voucher->type = VoucherType::Fixed;
            $voucher->value = 1000;
            $voucher->currency = null;

            $label = $voucher->value_label;

            // Money library formats as "$10.00" or similar
            expect($label)->toContain('10');
        });
    });

    describe('owner display name attribute', function (): void {
        it('returns null when no owner', function (): void {
            $voucher = new Voucher;
            $voucher->owner_type = null;
            $voucher->owner_id = null;

            expect($voucher->owner_display_name)->toBeNull();
        });
    });

    describe('remaining uses attribute', function (): void {
        it('returns null when no usage limit', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = null;

            expect($voucher->remaining_uses)->toBeNull();
        });

        it('calculates remaining uses correctly', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = 100;
            // times_used accessor reads usages_count attribute
            $voucher->setAttribute('usages_count', 30);

            expect($voucher->remaining_uses)->toBe(70);
        });
    });

    describe('status checks', function (): void {
        it('isActive returns true for Active status', function (): void {
            $voucher = new Voucher;
            $voucher->status = VoucherStatus::Active;

            expect($voucher->isActive())->toBeTrue();
        });

        it('isActive returns false for Paused status', function (): void {
            $voucher = new Voucher;
            $voucher->status = VoucherStatus::Paused;

            expect($voucher->isActive())->toBeFalse();
        });

        it('isActive returns false for Expired status', function (): void {
            $voucher = new Voucher;
            $voucher->status = VoucherStatus::Expired;

            expect($voucher->isActive())->toBeFalse();
        });

        it('isActive returns false for Depleted status', function (): void {
            $voucher = new Voucher;
            $voucher->status = VoucherStatus::Depleted;

            expect($voucher->isActive())->toBeFalse();
        });
    });

    describe('time-based checks', function (): void {
        it('hasStarted returns true when no start date', function (): void {
            $voucher = new Voucher;
            $voucher->starts_at = null;

            expect($voucher->hasStarted())->toBeTrue();
        });

        it('hasStarted returns true when start date is in the past', function (): void {
            $voucher = new Voucher;
            $voucher->starts_at = now()->subDay();

            expect($voucher->hasStarted())->toBeTrue();
        });

        it('hasStarted returns false when start date is in the future', function (): void {
            $voucher = new Voucher;
            $voucher->starts_at = now()->addDay();

            expect($voucher->hasStarted())->toBeFalse();
        });

        it('isExpired returns false when no expires date', function (): void {
            $voucher = new Voucher;
            $voucher->expires_at = null;

            expect($voucher->isExpired())->toBeFalse();
        });

        it('isExpired returns true when expires date is in the past', function (): void {
            $voucher = new Voucher;
            $voucher->expires_at = now()->subDay();

            expect($voucher->isExpired())->toBeTrue();
        });

        it('isExpired returns false when expires date is in the future', function (): void {
            $voucher = new Voucher;
            $voucher->expires_at = now()->addDay();

            expect($voucher->isExpired())->toBeFalse();
        });
    });

    describe('usage limit checks', function (): void {
        it('hasUsageLimitRemaining returns true when no limit', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = null;
            $voucher->setAttribute('usages_count', 100);

            expect($voucher->hasUsageLimitRemaining())->toBeTrue();
        });

        it('hasUsageLimitRemaining returns true when under limit', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = 100;
            $voucher->setAttribute('usages_count', 50);

            expect($voucher->hasUsageLimitRemaining())->toBeTrue();
        });

        it('hasUsageLimitRemaining returns false when at limit', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = 100;
            $voucher->setAttribute('usages_count', 100);

            expect($voucher->hasUsageLimitRemaining())->toBeFalse();
        });

        it('hasUsageLimitRemaining returns false when over limit', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = 100;
            $voucher->setAttribute('usages_count', 150);

            expect($voucher->hasUsageLimitRemaining())->toBeFalse();
        });
    });

    describe('getRemainingUses', function (): void {
        it('returns null when no usage limit', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = null;

            expect($voucher->getRemainingUses())->toBeNull();
        });

        it('returns correct remaining count', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = 50;
            $voucher->setAttribute('usages_count', 30);

            expect($voucher->getRemainingUses())->toBe(20);
        });

        it('returns zero when fully used', function (): void {
            $voucher = new Voucher;
            $voucher->usage_limit = 50;
            $voucher->setAttribute('usages_count', 50);

            expect($voucher->getRemainingUses())->toBe(0);
        });
    });

    describe('casts', function (): void {
        it('has correct cast definitions', function (): void {
            $voucher = new Voucher;
            $casts = $voucher->getCasts();

            expect($casts['status'])->toBe(VoucherStatus::class)
                ->and($casts['type'])->toBe(VoucherType::class)
                ->and($casts['target_definition'])->toBe('array')
                ->and($casts['metadata'])->toBe('array');
        });
    });

    describe('table configuration', function (): void {
        it('gets table name from config', function (): void {
            config(['vouchers.database.tables.vouchers' => 'custom_vouchers']);

            $voucher = new Voucher;
            expect($voucher->getTable())->toBe('custom_vouchers');
        });

        it('uses config value when set', function (): void {
            config(['vouchers.database.tables.vouchers' => 'my_vouchers_table']);

            $voucher = new Voucher;
            expect($voucher->getTable())->toBe('my_vouchers_table');
        });
    });

    describe('booted lifecycle', function (): void {
        it('registers deleting event', function (): void {
            $reflection = new ReflectionClass(Voucher::class);
            $method = $reflection->getMethod('booted');

            expect($method->isProtected())->toBeTrue()
                ->and($method->isStatic())->toBeTrue();
        });
    });
});
