<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Enums;

enum AuditEventType: string
{
    // Permission Events
    case PermissionGranted = 'permission.granted';
    case PermissionRevoked = 'permission.revoked';
    case PermissionCreated = 'permission.created';
    case PermissionDeleted = 'permission.deleted';
    case PermissionUpdated = 'permission.updated';

    // Role Events
    case RoleAssigned = 'role.assigned';
    case RoleUnassigned = 'role.unassigned';
    case RoleCreated = 'role.created';
    case RoleDeleted = 'role.deleted';
    case RoleUpdated = 'role.updated';
    case RolePermissionsUpdated = 'role.permissions_updated';

    // Policy Events
    case PolicyCreated = 'policy.created';
    case PolicyUpdated = 'policy.updated';
    case PolicyDeleted = 'policy.deleted';
    case PolicyActivated = 'policy.activated';
    case PolicyDeactivated = 'policy.deactivated';
    case PolicyEvaluated = 'policy.evaluated';

    // Access Events
    case AccessGranted = 'access.granted';
    case AccessDenied = 'access.denied';
    case AccessElevated = 'access.elevated';
    case AccessExpired = 'access.expired';

    // Group Events
    case GroupCreated = 'group.created';
    case GroupUpdated = 'group.updated';
    case GroupDeleted = 'group.deleted';
    case GroupPermissionsUpdated = 'group.permissions_updated';

    // Template Events
    case TemplateCreated = 'template.created';
    case TemplateUpdated = 'template.updated';
    case TemplateDeleted = 'template.deleted';
    case TemplateApplied = 'template.applied';

    // Scope Events
    case ScopedPermissionGranted = 'scoped.granted';
    case ScopedPermissionRevoked = 'scoped.revoked';
    case ScopedPermissionExpired = 'scoped.expired';

    // System Events
    case BulkPermissionSync = 'system.bulk_sync';
    case CacheCleared = 'system.cache_cleared';
    case ConfigurationChanged = 'system.config_changed';

    // Authentication Events
    case UserLogin = 'auth.login';
    case UserLogout = 'auth.logout';
    case LoginFailed = 'auth.login_failed';
    case PasswordChanged = 'auth.password_changed';
    case MfaFailed = 'auth.mfa_failed';

    // Security Events
    case SuspiciousActivity = 'security.suspicious';
    case PrivilegeEscalation = 'security.privilege_escalation';
    case UnauthorizedAccess = 'security.unauthorized';
    case BulkOperation = 'system.bulk_operation';

    // Role Removal
    case RoleRemoved = 'role.removed';

    // Snapshot Events
    case SnapshotCreated = 'snapshot.created';
    case SnapshotRestored = 'snapshot.restored';

    // Delegation Events
    case PermissionDelegated = 'delegation.granted';
    case PermissionDelegationRevoked = 'delegation.revoked';

    public function label(): string
    {
        return match ($this) {
            self::PermissionGranted => 'Permission Granted',
            self::PermissionRevoked => 'Permission Revoked',
            self::PermissionCreated => 'Permission Created',
            self::PermissionDeleted => 'Permission Deleted',
            self::PermissionUpdated => 'Permission Updated',
            self::RoleAssigned => 'Role Assigned',
            self::RoleUnassigned => 'Role Unassigned',
            self::RoleCreated => 'Role Created',
            self::RoleDeleted => 'Role Deleted',
            self::RoleUpdated => 'Role Updated',
            self::RolePermissionsUpdated => 'Role Permissions Updated',
            self::PolicyCreated => 'Policy Created',
            self::PolicyUpdated => 'Policy Updated',
            self::PolicyDeleted => 'Policy Deleted',
            self::PolicyActivated => 'Policy Activated',
            self::PolicyDeactivated => 'Policy Deactivated',
            self::PolicyEvaluated => 'Policy Evaluated',
            self::AccessGranted => 'Access Granted',
            self::AccessDenied => 'Access Denied',
            self::AccessElevated => 'Access Elevated',
            self::AccessExpired => 'Access Expired',
            self::GroupCreated => 'Group Created',
            self::GroupUpdated => 'Group Updated',
            self::GroupDeleted => 'Group Deleted',
            self::GroupPermissionsUpdated => 'Group Permissions Updated',
            self::TemplateCreated => 'Template Created',
            self::TemplateUpdated => 'Template Updated',
            self::TemplateDeleted => 'Template Deleted',
            self::TemplateApplied => 'Template Applied',
            self::ScopedPermissionGranted => 'Scoped Permission Granted',
            self::ScopedPermissionRevoked => 'Scoped Permission Revoked',
            self::ScopedPermissionExpired => 'Scoped Permission Expired',
            self::BulkPermissionSync => 'Bulk Permission Sync',
            self::CacheCleared => 'Cache Cleared',
            self::ConfigurationChanged => 'Configuration Changed',
            self::UserLogin => 'User Login',
            self::UserLogout => 'User Logout',
            self::LoginFailed => 'Login Failed',
            self::PasswordChanged => 'Password Changed',
            self::MfaFailed => 'MFA Failed',
            self::SuspiciousActivity => 'Suspicious Activity',
            self::PrivilegeEscalation => 'Privilege Escalation',
            self::UnauthorizedAccess => 'Unauthorized Access',
            self::BulkOperation => 'Bulk Operation',
            self::RoleRemoved => 'Role Removed',
            self::SnapshotCreated => 'Snapshot Created',
            self::SnapshotRestored => 'Snapshot Restored',
            self::PermissionDelegated => 'Permission Delegated',
            self::PermissionDelegationRevoked => 'Permission Delegation Revoked',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::PermissionGranted,
            self::PermissionRevoked,
            self::PermissionCreated,
            self::PermissionDeleted,
            self::PermissionUpdated => 'permission',

            self::RoleAssigned,
            self::RoleUnassigned,
            self::RoleCreated,
            self::RoleDeleted,
            self::RoleUpdated,
            self::RolePermissionsUpdated => 'role',

            self::PolicyCreated,
            self::PolicyUpdated,
            self::PolicyDeleted,
            self::PolicyActivated,
            self::PolicyDeactivated,
            self::PolicyEvaluated => 'policy',

            self::AccessGranted,
            self::AccessDenied,
            self::AccessElevated,
            self::AccessExpired => 'access',

            self::GroupCreated,
            self::GroupUpdated,
            self::GroupDeleted,
            self::GroupPermissionsUpdated => 'group',

            self::TemplateCreated,
            self::TemplateUpdated,
            self::TemplateDeleted,
            self::TemplateApplied => 'template',

            self::ScopedPermissionGranted,
            self::ScopedPermissionRevoked,
            self::ScopedPermissionExpired => 'scoped',

            self::BulkPermissionSync,
            self::CacheCleared,
            self::ConfigurationChanged,
            self::BulkOperation => 'system',

            self::UserLogin,
            self::UserLogout,
            self::LoginFailed,
            self::PasswordChanged,
            self::MfaFailed => 'auth',

            self::SuspiciousActivity,
            self::PrivilegeEscalation,
            self::UnauthorizedAccess => 'security',

            self::RoleRemoved => 'role',

            self::SnapshotCreated,
            self::SnapshotRestored => 'snapshot',

            self::PermissionDelegated,
            self::PermissionDelegationRevoked => 'delegation',
        };
    }

    public function icon(): string
    {
        return match ($this->category()) {
            'permission' => 'heroicon-o-key',
            'role' => 'heroicon-o-shield-check',
            'policy' => 'heroicon-o-document-text',
            'access' => 'heroicon-o-lock-closed',
            'group' => 'heroicon-o-folder',
            'template' => 'heroicon-o-document-duplicate',
            'scoped' => 'heroicon-o-adjustments-horizontal',
            'system' => 'heroicon-o-cog-6-tooth',
            'auth' => 'heroicon-o-user-circle',
            'security' => 'heroicon-o-shield-exclamation',
            'snapshot' => 'heroicon-o-camera',
            'delegation' => 'heroicon-o-arrow-path',
            default => 'heroicon-o-information-circle',
        };
    }

    public function defaultSeverity(): AuditSeverity
    {
        return match ($this) {
            self::AccessDenied,
            self::AccessElevated,
            self::RoleDeleted,
            self::PolicyDeleted,
            self::PermissionDeleted,
            self::SuspiciousActivity,
            self::PrivilegeEscalation,
            self::UnauthorizedAccess => AuditSeverity::High,

            self::PermissionGranted,
            self::PermissionRevoked,
            self::RoleAssigned,
            self::RoleUnassigned,
            self::RoleRemoved,
            self::PolicyCreated,
            self::PolicyUpdated,
            self::PolicyActivated,
            self::PolicyDeactivated,
            self::BulkPermissionSync,
            self::BulkOperation,
            self::LoginFailed,
            self::MfaFailed => AuditSeverity::Medium,

            default => AuditSeverity::Low,
        };
    }
}
