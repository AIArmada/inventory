<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\DelegationService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('permission versioning service can be instantiated', function (): void {
    $service = app(PermissionVersioningService::class);

    expect($service)->toBeInstanceOf(PermissionVersioningService::class);
});

test('delegation service can be instantiated', function (): void {
    $service = app(DelegationService::class);

    expect($service)->toBeInstanceOf(DelegationService::class);
});

test('permission request model constants are defined', function (): void {
    expect(PermissionRequest::STATUS_PENDING)->toBe('pending')
        ->and(PermissionRequest::STATUS_APPROVED)->toBe('approved')
        ->and(PermissionRequest::STATUS_DENIED)->toBe('denied')
        ->and(PermissionRequest::STATUS_EXPIRED)->toBe('expired');
});

test('permission request checks pending status', function (): void {
    $request = new PermissionRequest();
    $request->status = PermissionRequest::STATUS_PENDING;

    expect($request->isPending())->toBeTrue()
        ->and($request->isApproved())->toBeFalse()
        ->and($request->isDenied())->toBeFalse();
});

test('permission request checks approved status', function (): void {
    $request = new PermissionRequest();
    $request->status = PermissionRequest::STATUS_APPROVED;

    expect($request->isPending())->toBeFalse()
        ->and($request->isApproved())->toBeTrue()
        ->and($request->isDenied())->toBeFalse();
});

test('permission request checks denied status', function (): void {
    $request = new PermissionRequest();
    $request->status = PermissionRequest::STATUS_DENIED;

    expect($request->isPending())->toBeFalse()
        ->and($request->isApproved())->toBeFalse()
        ->and($request->isDenied())->toBeTrue();
});

test('permission request detects expired status', function (): void {
    $request = new PermissionRequest();
    $request->status = PermissionRequest::STATUS_PENDING;
    $request->expires_at = now()->subDay();

    expect($request->isExpired())->toBeTrue();
});

test('delegation model checks active status', function (): void {
    $delegation = new Delegation();
    $delegation->revoked_at = null;
    $delegation->expires_at = now()->addDay();

    expect($delegation->isActive())->toBeTrue()
        ->and($delegation->isExpired())->toBeFalse()
        ->and($delegation->isRevoked())->toBeFalse();
});

test('delegation model checks revoked status', function (): void {
    $delegation = new Delegation();
    $delegation->revoked_at = now();

    expect($delegation->isActive())->toBeFalse()
        ->and($delegation->isRevoked())->toBeTrue();
});

test('delegation model checks expired status', function (): void {
    $delegation = new Delegation();
    $delegation->revoked_at = null;
    $delegation->expires_at = now()->subDay();

    expect($delegation->isActive())->toBeFalse()
        ->and($delegation->isExpired())->toBeTrue();
});

test('permission snapshot model has correct table', function (): void {
    $snapshot = new PermissionSnapshot();

    expect($snapshot->getTable())->toContain('permission_snapshots');
});

test('permission request model has correct table', function (): void {
    $request = new PermissionRequest();

    expect($request->getTable())->toContain('permission_requests');
});

test('delegation model has correct table', function (): void {
    $delegation = new Delegation();

    expect($delegation->getTable())->toContain('delegations');
});
