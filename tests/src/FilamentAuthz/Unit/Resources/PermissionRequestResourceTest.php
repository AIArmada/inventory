<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\CreatePermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\EditPermissionRequest;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ListPermissionRequests;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource\Pages\ViewPermissionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PermissionRequestResource Metadata', function (): void {
    it('has correct model', function (): void {
        expect(PermissionRequestResource::getModel())->toBe(PermissionRequest::class);
    });

    it('has navigation icon', function (): void {
        expect(PermissionRequestResource::getNavigationIcon())->toBe('heroicon-o-clipboard-document-check');
    });

    it('has navigation label', function (): void {
        expect(PermissionRequestResource::getNavigationLabel())->toBe('Approval Requests');
    });

    it('has navigation sort', function (): void {
        expect(PermissionRequestResource::getNavigationSort())->toBe(40);
    });

    it('returns navigation group', function (): void {
        expect(PermissionRequestResource::getNavigationGroup())->toBe('Authorization');
    });

    it('has record title attribute', function (): void {
        expect(PermissionRequestResource::getRecordTitleAttribute())->toBe('id');
    });

    it('has empty relations', function (): void {
        expect(PermissionRequestResource::getRelations())->toBe([]);
    });
});

describe('PermissionRequestResource Pages', function (): void {
    it('has index page', function (): void {
        $pages = PermissionRequestResource::getPages();
        expect($pages)->toHaveKey('index')
            ->and($pages['index']->getPage())->toBe(ListPermissionRequests::class);
    });

    it('has create page', function (): void {
        $pages = PermissionRequestResource::getPages();
        expect($pages)->toHaveKey('create')
            ->and($pages['create']->getPage())->toBe(CreatePermissionRequest::class);
    });

    it('has view page', function (): void {
        $pages = PermissionRequestResource::getPages();
        expect($pages)->toHaveKey('view')
            ->and($pages['view']->getPage())->toBe(ViewPermissionRequest::class);
    });

    it('has edit page', function (): void {
        $pages = PermissionRequestResource::getPages();
        expect($pages)->toHaveKey('edit')
            ->and($pages['edit']->getPage())->toBe(EditPermissionRequest::class);
    });
});

describe('PermissionRequestResource canAccess', function (): void {
    it('returns false when approvals are disabled', function (): void {
        config(['filament-authz.enterprise.approvals.enabled' => false]);
        expect(PermissionRequestResource::canAccess())->toBeFalse();
    });

    it('returns true when approvals are enabled', function (): void {
        config(['filament-authz.enterprise.approvals.enabled' => true]);
        expect(PermissionRequestResource::canAccess())->toBeTrue();
    });
});

describe('PermissionRequestResource Navigation Badge', function (): void {
    it('returns null when no pending requests', function (): void {
        expect(PermissionRequestResource::getNavigationBadge())->toBeNull();
    });

    it('has badge color warning', function (): void {
        expect(PermissionRequestResource::getNavigationBadgeColor())->toBe('warning');
    });

    it('returns count when pending requests exist', function (): void {
        PermissionRequest::create([
            'requester_id' => '1',
            'requester_type' => 'App\\Models\\User',
            'requested_permissions' => ['user.view'],
            'justification' => 'Need access',
            'status' => 'pending',
        ]);

        expect(PermissionRequestResource::getNavigationBadge())->toBe('1');
    });

    it('excludes approved requests from count', function (): void {
        PermissionRequest::create([
            'requester_id' => '1',
            'requester_type' => 'App\\Models\\User',
            'requested_permissions' => ['user.view'],
            'justification' => 'Need access',
            'status' => 'approved',
        ]);

        expect(PermissionRequestResource::getNavigationBadge())->toBeNull();
    });

    it('excludes denied requests from count', function (): void {
        PermissionRequest::create([
            'requester_id' => '1',
            'requester_type' => 'App\\Models\\User',
            'requested_permissions' => ['user.view'],
            'justification' => 'Need access',
            'status' => 'denied',
        ]);

        expect(PermissionRequestResource::getNavigationBadge())->toBeNull();
    });
});
