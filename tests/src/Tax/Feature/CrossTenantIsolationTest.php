<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindTaxOwner(?Model $owner): void
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

it('blocks cross-tenant reads and writes when owner scoping is enabled', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    config()->set('tax.features.zone_resolution.unknown_zone_behavior', 'zero');

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-x@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-x@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerA);

    $zoneA = TaxZone::query()->create([
        'name' => 'Zone A',
        'code' => 'ZA',
        'is_active' => true,
        'is_default' => true,
    ]);

    TaxRate::query()->create([
        'zone_id' => $zoneA->id,
        'name' => 'Rate A',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZB',
        'is_active' => true,
        'is_default' => true,
    ]);

    bindTaxOwner($ownerA);

    $calculator = new TaxCalculator;

    $result = $calculator->calculateTax(10000, 'standard', $zoneB->id);

    expect($result->zone->id)
        ->toBe($zoneA->id);

    expect(fn () => TaxRate::query()->create([
        'zone_id' => $zoneB->id,
        'name' => 'Cross-tenant rate',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]))
        ->toThrow(AuthorizationException::class);
});
