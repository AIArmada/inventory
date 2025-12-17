<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Eloquent\Model;

function bindOwnerResolverForFilamentTax(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('scopes filament tax resources and badges to the current owner', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-tax-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-tax-owner-b@example.com',
        'password' => 'secret',
    ]);

    bindOwnerResolverForFilamentTax($ownerA);

    $zoneA = TaxZone::query()->create([
        'name' => 'Zone A',
        'code' => 'ZA',
        'is_active' => true,
    ]);

    $classA = TaxClass::query()->create([
        'name' => 'Standard A',
        'slug' => 'standard-a',
        'is_active' => true,
    ]);

    TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classA->id,
        'reason' => 'A',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    bindOwnerResolverForFilamentTax($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZB',
        'is_active' => true,
    ]);

    $classB = TaxClass::query()->create([
        'name' => 'Standard B',
        'slug' => 'standard-b',
        'is_active' => true,
    ]);

    TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classB->id,
        'reason' => 'B',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    bindOwnerResolverForFilamentTax($ownerA);

    expect(TaxExemptionResource::getNavigationBadge())->toBe('1');

    expect(TaxZoneResource::getEloquentQuery()->whereKey($zoneA->id)->exists())->toBeTrue()
        ->and(TaxZoneResource::getEloquentQuery()->whereKey($zoneB->id)->exists())->toBeFalse();
});
