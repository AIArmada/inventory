<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

it('scopes attributions to current owner and global when enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::create(['name' => 'Owner A']);
    $ownerB = TestOwner::create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $affiliate = Affiliate::create([
        'code' => 'AFF-OWNER-A',
        'name' => 'Owned Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $global = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'global-cookie',
        'cart_instance' => 'default',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $ownedA = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'owner-a-cookie',
        'cart_instance' => 'default',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $ownedB = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'owner-b-cookie',
        'cart_instance' => 'default',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $corrupt = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'corrupt-cookie',
        'cart_instance' => 'default',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
    ]);

    $ids = AffiliateAttribution::query()->forOwner()->pluck('id');

    expect($ids)->toContain($global->id)
        ->and($ids)->toContain($ownedA->id)
        ->and($ids)->not->toContain($ownedB->id)
        ->and($ids)->not->toContain($corrupt->id);
});

it('scopes conversions to current owner and global when enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::create(['name' => 'Owner A']);
    $ownerB = TestOwner::create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $affiliate = Affiliate::create([
        'code' => 'AFF-OWNER-A-2',
        'name' => 'Owned Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $global = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'GLOBAL',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $ownedA = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'OWN-A',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $ownedB = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'OWN-B',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $corrupt = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'CORRUPT',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
    ]);

    $ids = AffiliateConversion::query()->forOwner()->pluck('id');

    expect($ids)->toContain($global->id)
        ->and($ids)->toContain($ownedA->id)
        ->and($ids)->not->toContain($ownedB->id)
        ->and($ids)->not->toContain($corrupt->id);
});

it('returns strict global-only when enabled and resolved owner is null', function (): void {
    config()->set('affiliates.owner.enabled', true);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $owner = TestOwner::create(['name' => 'Owner']);

    $affiliate = Affiliate::create([
        'code' => 'AFF-NULL-OWNER',
        'name' => 'Owned Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $globalAttribution = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'global-only-attribution',
        'cart_instance' => 'default',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'owned-attribution',
        'cart_instance' => 'default',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $corruptAttribution = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'corrupt-attribution',
        'cart_instance' => 'default',
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => null,
    ]);

    $globalConversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'GLOBAL',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'OWNED',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);

    $corruptConversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'CORRUPT',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => null,
    ]);

    $attributionIds = AffiliateAttribution::query()->forOwner()->pluck('id');
    $conversionIds = AffiliateConversion::query()->forOwner()->pluck('id');

    expect($attributionIds)->toContain($globalAttribution->id)
        ->and($attributionIds)->not->toContain($corruptAttribution->id)
        ->and($attributionIds)->toHaveCount(1);

    expect($conversionIds)->toContain($globalConversion->id)
        ->and($conversionIds)->not->toContain($corruptConversion->id)
        ->and($conversionIds)->toHaveCount(1);
});

class TestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
