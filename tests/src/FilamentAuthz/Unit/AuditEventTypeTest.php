<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditEventType;

test('audit event type includes snapshot events', function (): void {
    expect(AuditEventType::SnapshotCreated->value)->toBe('snapshot.created')
        ->and(AuditEventType::SnapshotRestored->value)->toBe('snapshot.restored');
});

test('audit event type includes delegation events', function (): void {
    expect(AuditEventType::PermissionDelegated->value)->toBe('delegation.granted')
        ->and(AuditEventType::PermissionDelegationRevoked->value)->toBe('delegation.revoked');
});

test('audit event type snapshot events have labels', function (): void {
    expect(AuditEventType::SnapshotCreated->label())->toBe('Snapshot Created')
        ->and(AuditEventType::SnapshotRestored->label())->toBe('Snapshot Restored');
});

test('audit event type delegation events have labels', function (): void {
    expect(AuditEventType::PermissionDelegated->label())->toBe('Permission Delegated')
        ->and(AuditEventType::PermissionDelegationRevoked->label())->toBe('Permission Delegation Revoked');
});

test('audit event type snapshot events have correct category', function (): void {
    expect(AuditEventType::SnapshotCreated->category())->toBe('snapshot')
        ->and(AuditEventType::SnapshotRestored->category())->toBe('snapshot');
});

test('audit event type delegation events have correct category', function (): void {
    expect(AuditEventType::PermissionDelegated->category())->toBe('delegation')
        ->and(AuditEventType::PermissionDelegationRevoked->category())->toBe('delegation');
});

test('audit event type snapshot events have icons', function (): void {
    $icon = AuditEventType::SnapshotCreated->icon();
    expect($icon)->toBe('heroicon-o-camera');
});

test('audit event type delegation events have icons', function (): void {
    $icon = AuditEventType::PermissionDelegated->icon();
    expect($icon)->toBe('heroicon-o-arrow-path');
});
