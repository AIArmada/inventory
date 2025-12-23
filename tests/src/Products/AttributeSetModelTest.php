<?php

declare(strict_types=1);

use AIArmada\Products\Models\AttributeSet;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

describe('AttributeSet Model', function (): void {
    beforeEach(function (): void {
        OwnerContext::clearOverride();

        app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
        {
            public function resolve(): ?Model
            {
                return null;
            }
        });
    });

    it('scopes setAsDefault() updates to the same owner', function (): void {
        $ownerType = 'tenant';
        $ownerAId = (string) Str::uuid();
        $ownerBId = (string) Str::uuid();

        $ownerASet1 = AttributeSet::create([
            'owner_type' => $ownerType,
            'owner_id' => $ownerAId,
            'name' => 'Owner A Default',
            'code' => 'owner-a-default-' . Str::uuid(),
            'is_default' => true,
        ]);

        $ownerASet2 = AttributeSet::create([
            'owner_type' => $ownerType,
            'owner_id' => $ownerAId,
            'name' => 'Owner A New Default',
            'code' => 'owner-a-new-default-' . Str::uuid(),
            'is_default' => false,
        ]);

        $ownerBSet = AttributeSet::create([
            'owner_type' => $ownerType,
            'owner_id' => $ownerBId,
            'name' => 'Owner B Default',
            'code' => 'owner-b-default-' . Str::uuid(),
            'is_default' => true,
        ]);

        $ownerASet2->setAsDefault();

        expect($ownerASet1->refresh()->is_default)->toBeFalse()
            ->and($ownerASet2->refresh()->is_default)->toBeTrue()
            ->and($ownerBSet->refresh()->is_default)->toBeTrue();
    });

    it('scopes setAsDefault() updates to global sets when owner is null', function (): void {
        $ownerType = 'tenant';
        $ownerId = (string) Str::uuid();

        $globalSet1 = AttributeSet::create([
            'name' => 'Global Default',
            'code' => 'global-default-' . Str::uuid(),
            'is_default' => true,
        ]);

        $globalSet2 = AttributeSet::create([
            'name' => 'Global New Default',
            'code' => 'global-new-default-' . Str::uuid(),
            'is_default' => false,
        ]);

        $ownedSet = AttributeSet::create([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'name' => 'Owned Default',
            'code' => 'owned-default-' . Str::uuid(),
            'is_default' => true,
        ]);

        $globalSet2->setAsDefault();

        expect($globalSet1->refresh()->is_default)->toBeFalse()
            ->and($globalSet2->refresh()->is_default)->toBeTrue()
            ->and($ownedSet->refresh()->is_default)->toBeTrue();
    });
});
