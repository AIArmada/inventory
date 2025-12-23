<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentPricing\Widgets\PricingStatsWidget;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Promotion;
use Illuminate\Database\Eloquent\Model;

function bindFilamentPricingOwner(?Model $owner): void
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

it('scopes filament-pricing dashboard stats to the current owner (optionally including global)', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-pricing-owner-a-xt@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-pricing-owner-b-xt@example.com',
        'password' => 'secret',
    ]);

    // Global rows are created with no owner context.
    bindFilamentPricingOwner(null);

    PriceList::query()->create([
        'name' => 'Global List',
        'slug' => 'global-list-xt',
        'currency' => 'MYR',
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $globalPromotion = Promotion::query()->create([
        'name' => 'Global Promo',
        'code' => 'GLOBAL-XT',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $globalPromotion->forceFill(['usage_count' => 7])->save();

    bindFilamentPricingOwner($ownerA);

    PriceList::query()->create([
        'name' => 'Owner A List',
        'slug' => 'owner-a-list-xt',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $ownerAPromotion = Promotion::query()->create([
        'name' => 'Owner A Promo',
        'code' => 'A-XT',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    $ownerAPromotion->forceFill(['usage_count' => 3])->save();

    bindFilamentPricingOwner($ownerB);

    PriceList::query()->create([
        'name' => 'Owner B List',
        'slug' => 'owner-b-list-xt',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $ownerBPromotion = Promotion::query()->create([
        'name' => 'Owner B Promo',
        'code' => 'B-XT',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    $ownerBPromotion->forceFill(['usage_count' => 5])->save();

    bindFilamentPricingOwner($ownerA);

    $widget = app(PricingStatsWidget::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');
    $method->setAccessible(true);

    /** @var array<int, Filament\Widgets\StatsOverviewWidget\Stat> $stats */
    $stats = $method->invoke($widget);

    expect($stats[0]->getValue())->toBe('2')
        ->and($stats[1]->getValue())->toBe('2')
        ->and($stats[2]->getValue())->toBe('10');
});
