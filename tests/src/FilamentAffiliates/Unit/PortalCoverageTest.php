<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPayouts;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    AffiliateAttribution::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
});

it('portal pages do not leak cross-tenant data when owner mode enabled', function (): void {
    config([
        'affiliates.owner.enabled' => true,
    ]);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $affiliateB = Affiliate::create([
        'code' => 'PORTAL-B-' . Str::uuid(),
        'name' => 'Portal Affiliate B',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'order_reference' => 'ORDER-B-001',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-B-' . Str::uuid(),
        'status' => 'completed',
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliateB->getMorphClass(),
        'owner_id' => $affiliateB->getKey(),
        'paid_at' => now(),
    ]);

    $dashboard = new PortalDashboard;
    $dashboardData = $dashboard->getViewData();

    expect($dashboardData['hasAffiliate'])->toBeFalse()
        ->and($dashboardData['totalClicks'])->toBe(0)
        ->and($dashboardData['totalConversions'])->toBe(0)
        ->and($dashboardData['totalEarnings'])->toBe(0)
        ->and($dashboardData['pendingEarnings'])->toBe(0);

    $conversions = new PortalConversions;
    $conversionsData = $conversions->getViewData();

    expect($conversionsData['hasAffiliate'])->toBeFalse()
        ->and($conversionsData['totalConversions'])->toBe(0)
        ->and($conversionsData['totalEarnings'])->toBe(0)
        ->and($conversionsData['pendingEarnings'])->toBe(0);

    $payouts = new PortalPayouts;
    $payoutsData = $payouts->getViewData();

    expect($payoutsData['hasAffiliate'])->toBeFalse()
        ->and($payoutsData['totalPaid'])->toBe(0);
});

it('portal pages only return current owner affiliate stats when multiple owners exist', function (): void {
    config([
        'affiliates.owner.enabled' => true,
    ]);

    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a2-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b2-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $affiliateA = Affiliate::create([
        'code' => 'PORTAL-A-' . Str::uuid(),
        'name' => 'Portal Affiliate A',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    $affiliateB = Affiliate::create([
        'code' => 'PORTAL-B2-' . Str::uuid(),
        'name' => 'Portal Affiliate B',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'cart_instance' => 'default',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'cart_instance' => 'default',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'order_reference' => 'ORDER-A-001',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'order_reference' => 'ORDER-B-001',
        'total_minor' => 10000,
        'commission_minor' => 9999,
        'commission_currency' => 'USD',
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-A-' . Str::uuid(),
        'status' => 'completed',
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliateA->getMorphClass(),
        'owner_id' => $affiliateA->getKey(),
        'paid_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-B-' . Str::uuid(),
        'status' => 'completed',
        'total_minor' => 9999,
        'currency' => 'USD',
        'owner_type' => $affiliateB->getMorphClass(),
        'owner_id' => $affiliateB->getKey(),
        'paid_at' => now(),
    ]);

    $dashboard = new PortalDashboard;
    $dashboardData = $dashboard->getViewData();

    expect($dashboardData['hasAffiliate'])->toBeTrue()
        ->and($dashboardData['totalClicks'])->toBe(1)
        ->and($dashboardData['totalConversions'])->toBe(1)
        ->and($dashboardData['totalEarnings'])->toBe(1000);

    $payouts = new PortalPayouts;
    $payoutsData = $payouts->getViewData();

    expect($payoutsData['hasAffiliate'])->toBeTrue()
        ->and($payoutsData['totalPaid'])->toBe(1500);
});

it('portal pages return scoped view data when affiliate exists', function (): void {
    $user = User::create([
        'name' => 'Affiliate User',
        'email' => 'affiliate-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'PORTAL-' . Str::uuid(),
        'name' => 'Portal Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliateAttribution::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'cart_instance' => 'default',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-001',
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORDER-002',
        'total_minor' => 5000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => 'pending',
        'occurred_at' => now(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAYOUT-' . Str::uuid(),
        'status' => 'completed',
        'total_minor' => 1500,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
        'paid_at' => now(),
    ]);

    $this->actingAs($user);

    $dashboard = new PortalDashboard;
    $dashboardData = $dashboard->getViewData();

    expect($dashboardData['hasAffiliate'])->toBeTrue()
        ->and($dashboardData['totalClicks'])->toBe(1)
        ->and($dashboardData['totalConversions'])->toBe(2)
        ->and($dashboardData['totalEarnings'])->toBe(1000)
        ->and($dashboardData['pendingEarnings'])->toBe(500);

    $conversions = new PortalConversions;
    $conversionsData = $conversions->getViewData();

    expect($conversionsData['hasAffiliate'])->toBeTrue()
        ->and($conversionsData['totalConversions'])->toBe(2)
        ->and($conversionsData['totalEarnings'])->toBe(1000)
        ->and($conversionsData['pendingEarnings'])->toBe(500);

    $payouts = new PortalPayouts;
    $payoutsData = $payouts->getViewData();

    expect($payoutsData['hasAffiliate'])->toBeTrue()
        ->and($payoutsData['totalPaid'])->toBe(1500);
});

it('PortalConversions configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();

    $page = new PortalConversions;
    $page->table($table);

    expect(true)->toBeTrue();
});

it('PortalPayouts configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('defaultSort')->once()->andReturnSelf();
    $table->shouldReceive('paginated')->once()->andReturnSelf();

    $page = new PortalPayouts;
    $page->table($table);

    expect(true)->toBeTrue();
});

it('PortalLinks generates links when affiliate exists', function (): void {
    $user = User::create([
        'name' => 'Link User',
        'email' => 'link-user@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'LINK-' . Str::uuid(),
        'name' => 'Link Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $this->app->instance(AffiliateLinkGenerator::class, new class
    {
        public function generate(string $affiliateCode, string $url): string
        {
            return $url . '?aff=' . $affiliateCode;
        }
    });

    $page = new PortalLinks;
    $page->mount();

    expect($page->getDefaultLink())->toContain($affiliate->code);

    $page->targetUrl = url('/test');
    $page->generateLink();

    expect($page->generatedLink)->toBe(url('/test') . '?aff=' . $affiliate->code);

    $reflection = new ReflectionClass($page);
    $method = $reflection->getMethod('getHeaderActions');
    $method->setAccessible(true);

    $actions = $method->invoke($page);
    expect($actions)->toBeArray()->and(count($actions))->toBe(1);
});

it('PortalLinks falls back when link generator rejects the default URL', function (): void {
    $user = User::create([
        'name' => 'Fallback User',
        'email' => 'fallback-user@example.com',
        'password' => 'secret',
    ]);

    Affiliate::create([
        'code' => 'FALLBACK-' . Str::uuid(),
        'name' => 'Fallback Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $user->getMorphClass(),
        'owner_id' => (string) $user->getKey(),
    ]);

    $this->actingAs($user);

    $this->app->instance(AffiliateLinkGenerator::class, new class
    {
        public function generate(string $affiliateCode, string $url): string
        {
            throw new \InvalidArgumentException('Disallowed URL');
        }
    });

    $page = new PortalLinks;

    $fallback = $page->getDefaultLink();

    expect($fallback)->toContain('?');
});

it('PortalRegistration blocks register when disabled', function (): void {
    $registration = new PortalRegistration;

    $reflection = new ReflectionClass($registration);
    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($registration, false);

    expect($registration->register())->toBeNull();
});

it('PortalRegistration subheading reflects approval mode', function (): void {
    $registration = new PortalRegistration;

    $reflection = new ReflectionClass($registration);

    $enabled = $reflection->getProperty('registrationEnabled');
    $enabled->setAccessible(true);
    $enabled->setValue($registration, true);

    $mode = $reflection->getProperty('approvalMode');
    $mode->setAccessible(true);

    $mode->setValue($registration, 'auto');
    expect($registration->getSubheading())->toContain('automatically');

    $mode->setValue($registration, 'open');
    expect($registration->getSubheading())->toContain('pending');

    $mode->setValue($registration, 'admin');
    expect($registration->getSubheading())->toContain('reviewed');
});
