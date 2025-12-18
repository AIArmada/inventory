<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    // Clear audit logs
    PermissionAuditLog::query()->delete();
    Role::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Enable audit logging for tests
    config(['filament-authz.audit.enabled' => true]);
    config(['filament-authz.audit.async' => false]);

    // Create and authenticate a user for all tests
    $user = User::create([
        'name' => 'System User',
        'email' => 'system@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('AuditLogger Service', function (): void {
    test('can be instantiated', function (): void {
        $logger = new AuditLogger();
        expect($logger)->toBeInstanceOf(AuditLogger::class);
    });

    test('log method creates audit entry when enabled', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Test Subject',
            'email' => 'subject@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->log(
            eventType: AuditEventType::PermissionGranted,
            subject: $subject,
            newValues: ['permission' => 'test.permission']
        );

        expect(PermissionAuditLog::count())->toBe(1);
    });

    test('log method respects disabled config', function (): void {
        config(['filament-authz.audit.enabled' => false]);

        $logger = new AuditLogger();
        $logger->log(AuditEventType::PermissionGranted);

        expect(PermissionAuditLog::count())->toBe(0);
    });

    test('logPermissionGranted creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logPermissionGranted($subject, 'users.create');

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::PermissionGranted->value);
        expect($log->new_value)->toBe(['permission' => 'users.create']);
    });

    test('logPermissionRevoked creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logPermissionRevoked($subject, 'users.delete');

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::PermissionRevoked->value);
        expect($log->old_value)->toBe(['permission' => 'users.delete']);
    });

    test('logRoleAssigned creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logRoleAssigned($subject, 'Admin');

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::RoleAssigned->value);
        expect($log->new_value)->toBe(['role' => 'Admin']);
    });

    test('logRoleRemoved creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logRoleRemoved($subject, 'Editor');

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::RoleRemoved->value);
        expect($log->old_value)->toBe(['role' => 'Editor']);
    });

    test('logRoleCreated creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $role = Role::create(['name' => 'NewRole', 'guard_name' => 'web']);

        $logger->logRoleCreated($role);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::RoleCreated->value);
        expect($log->new_value)->toBe(['name' => 'NewRole']);
    });

    test('logRoleDeleted creates high severity entry', function (): void {
        $logger = new AuditLogger();

        $role = Role::create(['name' => 'ToDelete', 'guard_name' => 'web']);

        $logger->logRoleDeleted($role);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::RoleDeleted->value);
        expect($log->severity)->toBe(AuditSeverity::High->value);
    });

    test('logAccessDenied creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logAccessDenied($subject, 'admin.access', null, ['route' => 'admin.dashboard']);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::AccessDenied->value);
        expect($log->context)->toHaveKey('permission');
        expect($log->context)->toHaveKey('route');
    });

    test('logPolicyEvaluated creates correct audit entry', function (): void {
        $logger = new AuditLogger();

        $logger->logPolicyEvaluated('view', 'User', [
            'result' => true,
            'rules_evaluated' => 3,
        ]);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::PolicyEvaluated->value);
        expect($log->context)->toHaveKey('action');
        expect($log->context)->toHaveKey('resource');
        expect($log->context)->toHaveKey('evaluation');
    });

    test('logSuspiciousActivity creates critical severity entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Suspicious User',
            'email' => 'suspicious@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logSuspiciousActivity($subject, 'multiple_failed_logins', [
            'attempts' => 10,
            'timeframe' => '5 minutes',
        ]);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::SuspiciousActivity->value);
        expect($log->severity)->toBe(AuditSeverity::Critical->value);
    });

    test('logPrivilegeEscalation creates critical severity entry', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Escalating User',
            'email' => 'escalate@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->logPrivilegeEscalation($subject, ['super_admin', 'delete_all']);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->event_type)->toBe(AuditEventType::PrivilegeEscalation->value);
        expect($log->severity)->toBe(AuditSeverity::Critical->value);
        expect($log->new_value)->toBe(['privileges' => ['super_admin', 'delete_all']]);
    });

    test('logBulkOperation sets severity based on affected count', function (): void {
        $logger = new AuditLogger();

        // Low count - medium severity
        $logger->logBulkOperation('delete_users', 50);

        $log1 = PermissionAuditLog::first();
        expect($log1->severity)->toBe(AuditSeverity::Medium->value);

        // High count - high severity
        PermissionAuditLog::query()->delete();
        $logger->logBulkOperation('reset_permissions', 150);

        $log2 = PermissionAuditLog::first();
        expect($log2->severity)->toBe(AuditSeverity::High->value);
    });

    test('log enriches metadata with request info', function (): void {
        $logger = new AuditLogger();

        $subject = User::create([
            'name' => 'Metadata Test User',
            'email' => 'metadata@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->log(AuditEventType::PermissionGranted, subject: $subject);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->context)->toHaveKey('ip_address');
        expect($log->context)->toHaveKey('user_agent');
        expect($log->context)->toHaveKey('url');
        expect($log->context)->toHaveKey('method');
    });

    test('log captures authenticated user as actor', function (): void {
        $logger = new AuditLogger();

        $actor = User::first(); // The system user from beforeEach
        $subject = User::create([
            'name' => 'Subject User',
            'email' => 'actor-test@example.com',
            'password' => bcrypt('password'),
        ]);

        $logger->log(AuditEventType::PermissionGranted, subject: $subject);

        $log = PermissionAuditLog::first();
        expect($log)->not->toBeNull();
        expect($log->actor_id)->toBe((string) $actor->id);
    });
});
