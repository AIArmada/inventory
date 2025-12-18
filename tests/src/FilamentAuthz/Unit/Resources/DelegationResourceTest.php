<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\CreateDelegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\EditDelegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ListDelegations;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ViewDelegation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DelegationResource Metadata', function (): void {
    it('has correct model', function (): void {
        expect(DelegationResource::getModel())->toBe(Delegation::class);
    });

    it('has navigation icon', function (): void {
        expect(DelegationResource::getNavigationIcon())->toBe('heroicon-o-arrows-right-left');
    });

    it('has navigation label', function (): void {
        expect(DelegationResource::getNavigationLabel())->toBe('Delegations');
    });

    it('has navigation sort', function (): void {
        expect(DelegationResource::getNavigationSort())->toBe(45);
    });

    it('returns navigation group', function (): void {
        expect(DelegationResource::getNavigationGroup())->toBe('Authorization');
    });

    it('has record title attribute', function (): void {
        expect(DelegationResource::getRecordTitleAttribute())->toBe('id');
    });

    it('has empty relations', function (): void {
        expect(DelegationResource::getRelations())->toBe([]);
    });
});

describe('DelegationResource Pages', function (): void {
    it('has index page', function (): void {
        $pages = DelegationResource::getPages();
        expect($pages)->toHaveKey('index')
            ->and($pages['index']->getPage())->toBe(ListDelegations::class);
    });

    it('has create page', function (): void {
        $pages = DelegationResource::getPages();
        expect($pages)->toHaveKey('create')
            ->and($pages['create']->getPage())->toBe(CreateDelegation::class);
    });

    it('has view page', function (): void {
        $pages = DelegationResource::getPages();
        expect($pages)->toHaveKey('view')
            ->and($pages['view']->getPage())->toBe(ViewDelegation::class);
    });

    it('has edit page', function (): void {
        $pages = DelegationResource::getPages();
        expect($pages)->toHaveKey('edit')
            ->and($pages['edit']->getPage())->toBe(EditDelegation::class);
    });
});

describe('DelegationResource canAccess', function (): void {
    it('returns false when delegation is disabled', function (): void {
        config(['filament-authz.enterprise.delegation.enabled' => false]);
        expect(DelegationResource::canAccess())->toBeFalse();
    });

    it('returns true when delegation is enabled', function (): void {
        config(['filament-authz.enterprise.delegation.enabled' => true]);
        expect(DelegationResource::canAccess())->toBeTrue();
    });
});

describe('DelegationResource Navigation Badge', function (): void {
    it('returns null when no active delegations', function (): void {
        expect(DelegationResource::getNavigationBadge())->toBeNull();
    });

    it('has badge color info', function (): void {
        expect(DelegationResource::getNavigationBadgeColor())->toBe('info');
    });

    it('returns count when active delegations exist', function (): void {
        Delegation::create([
            'delegator_id' => '1',
            'delegator_type' => 'App\\Models\\User',
            'delegatee_id' => '2',
            'delegatee_type' => 'App\\Models\\User',
            'permission' => 'user.view',
            'can_redelegate' => false,
        ]);

        expect(DelegationResource::getNavigationBadge())->toBe('1');
    });

    it('excludes revoked delegations from count', function (): void {
        Delegation::create([
            'delegator_id' => '1',
            'delegator_type' => 'App\\Models\\User',
            'delegatee_id' => '2',
            'delegatee_type' => 'App\\Models\\User',
            'permission' => 'user.view',
            'can_redelegate' => false,
            'revoked_at' => now(),
        ]);

        expect(DelegationResource::getNavigationBadge())->toBeNull();
    });

    it('excludes expired delegations from count', function (): void {
        Delegation::create([
            'delegator_id' => '1',
            'delegator_type' => 'App\\Models\\User',
            'delegatee_id' => '2',
            'delegatee_type' => 'App\\Models\\User',
            'permission' => 'user.view',
            'can_redelegate' => false,
            'expires_at' => now()->subDay(),
        ]);

        expect(DelegationResource::getNavigationBadge())->toBeNull();
    });
});
