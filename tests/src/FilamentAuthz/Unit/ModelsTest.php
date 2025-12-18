<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Models\RoleTemplate;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

describe('AccessPolicy model', function (): void {
    it('uses HasUuids trait', function (): void {
        $model = new AccessPolicy;

        expect(in_array(HasUuids::class, class_uses_recursive($model)))->toBeTrue();
    });

    it('has correct fillable attributes', function (): void {
        $model = new AccessPolicy;

        expect($model->getFillable())->toContain('name')
            ->toContain('slug')
            ->toContain('effect')
            ->toContain('target_action')
            ->toContain('conditions')
            ->toContain('priority')
            ->toContain('is_active');
    });

    it('gets effect as enum', function (): void {
        $model = new AccessPolicy;
        $model->effect = 'allow';

        expect($model->getEffectEnum())->toBe(PolicyEffect::Allow);

        $model->effect = 'deny';
        expect($model->getEffectEnum())->toBe(PolicyEffect::Deny);

        $model->effect = 'invalid';
        expect($model->getEffectEnum())->toBe(PolicyEffect::Deny);
    });

    it('validates policy is active and within date range', function (): void {
        $model = new AccessPolicy;
        $model->is_active = false;

        expect($model->isValid())->toBeFalse();

        $model->is_active = true;
        $model->valid_from = null;
        $model->valid_until = null;

        expect($model->isValid())->toBeTrue();

        $model->valid_from = Carbon::now()->subDay();
        expect($model->isValid())->toBeTrue();

        $model->valid_from = Carbon::now()->addDay();
        expect($model->isValid())->toBeFalse();

        $model->valid_from = Carbon::now()->subDay();
        $model->valid_until = Carbon::now()->addDay();
        expect($model->isValid())->toBeTrue();

        $model->valid_until = Carbon::now()->subHour();
        expect($model->isValid())->toBeFalse();
    });

    it('checks if policy applies to action', function (): void {
        $model = new AccessPolicy;
        $model->target_action = 'orders.create';
        $model->target_resource = null;

        expect($model->appliesTo('orders.create'))->toBeTrue();
        expect($model->appliesTo('orders.delete'))->toBeFalse();

        $model->target_action = '*';
        expect($model->appliesTo('any.action'))->toBeTrue();

        $model->target_action = 'orders.*';
        expect($model->appliesTo('orders.create'))->toBeTrue();
        expect($model->appliesTo('orders.delete'))->toBeTrue();
        expect($model->appliesTo('products.create'))->toBeFalse();
    });

    it('checks resource matching', function (): void {
        $model = new AccessPolicy;
        $model->target_action = '*';
        $model->target_resource = 'Order';

        expect($model->appliesTo('orders.create', 'Order'))->toBeTrue();
        expect($model->appliesTo('orders.create', 'Product'))->toBeFalse();

        $model->target_resource = null;
        expect($model->appliesTo('orders.create', 'Anything'))->toBeTrue();

        $model->target_resource = '*';
        expect($model->appliesTo('orders.create', 'Anything'))->toBeTrue();
    });

    it('evaluates conditions correctly', function (): void {
        $model = new AccessPolicy;
        $model->is_active = true;
        $model->effect = 'allow';
        $model->conditions = [];

        expect($model->evaluateConditions([]))->toBeTrue();

        $model->conditions = [
            ['attribute' => 'role', 'operator' => 'eq', 'value' => 'admin'],
        ];

        expect($model->evaluateConditions(['role' => 'admin']))->toBeTrue();
        expect($model->evaluateConditions(['role' => 'user']))->toBeFalse();
    });

    it('evaluates policy and returns decision', function (): void {
        $model = new AccessPolicy;
        $model->is_active = true;
        $model->effect = 'allow';
        $model->conditions = [];

        expect($model->evaluate([]))->toBe(PolicyDecision::Permit);

        $model->effect = 'deny';
        expect($model->evaluate([]))->toBe(PolicyDecision::Deny);

        $model->is_active = false;
        expect($model->evaluate([]))->toBe(PolicyDecision::NotApplicable);
    });

    it('has correct casts', function (): void {
        $model = new AccessPolicy;
        $casts = $model->getCasts();

        expect($casts['conditions'])->toBe('array');
        expect($casts['metadata'])->toBe('array');
        expect($casts['priority'])->toBe('integer');
        expect($casts['is_active'])->toBe('boolean');
        expect($casts['valid_from'])->toBe('datetime');
        expect($casts['valid_until'])->toBe('datetime');
    });
});

describe('ScopedPermission model', function (): void {
    it('uses HasUuids trait', function (): void {
        $model = new ScopedPermission;

        expect(in_array(HasUuids::class, class_uses_recursive($model)))->toBeTrue();
    });

    it('has correct fillable attributes', function (): void {
        $model = new ScopedPermission;

        expect($model->getFillable())->toContain('permission_id')
            ->toContain('permissionable_type')
            ->toContain('permissionable_id')
            ->toContain('scope_type')
            ->toContain('scope_id')
            ->toContain('conditions')
            ->toContain('granted_at')
            ->toContain('expires_at');
    });

    it('gets scope enum', function (): void {
        $model = new ScopedPermission;
        $model->scope_type = 'global';

        expect($model->getScopeEnum())->toBe(PermissionScope::Global);

        $model->scope_type = 'team';
        expect($model->getScopeEnum())->toBe(PermissionScope::Team);

        $model->scope_type = 'invalid';
        expect($model->getScopeEnum())->toBe(PermissionScope::Global);
    });

    it('checks if expired', function (): void {
        $model = new ScopedPermission;

        expect($model->isExpired())->toBeFalse();

        $model->expires_at = Carbon::now()->subDay();
        expect($model->isExpired())->toBeTrue();

        $model->expires_at = Carbon::now()->addDay();
        expect($model->isExpired())->toBeFalse();
    });

    it('checks if active', function (): void {
        $model = new ScopedPermission;

        expect($model->isActive())->toBeTrue();

        $model->expires_at = Carbon::now()->subDay();
        expect($model->isActive())->toBeFalse();
    });

    it('matches context for global scope', function (): void {
        $model = new ScopedPermission;
        $model->scope_type = 'global';

        expect($model->matchesContext([]))->toBeTrue();
        expect($model->matchesContext(['team_id' => '123']))->toBeTrue();
    });

    it('matches context for team scope', function (): void {
        $model = new ScopedPermission;
        $model->scope_type = 'team';
        $model->scope_id = 'team-123';

        expect($model->matchesContext(['team_id' => 'team-123']))->toBeTrue();
        expect($model->matchesContext(['team_id' => 'team-456']))->toBeFalse();
        expect($model->matchesContext([]))->toBeFalse();
    });

    it('evaluates conditions', function (): void {
        $model = new ScopedPermission;
        $model->conditions = [];

        expect($model->evaluateConditions([]))->toBeTrue();

        $model->conditions = [
            ['attribute' => 'status', 'operator' => 'eq', 'value' => 'active'],
        ];

        expect($model->evaluateConditions(['status' => 'active']))->toBeTrue();
        expect($model->evaluateConditions(['status' => 'inactive']))->toBeFalse();
    });

    it('evaluates various condition operators', function (): void {
        $model = new ScopedPermission;

        $model->conditions = [['attribute' => 'age', 'operator' => 'gt', 'value' => 18]];
        expect($model->evaluateConditions(['age' => 25]))->toBeTrue();
        expect($model->evaluateConditions(['age' => 15]))->toBeFalse();

        $model->conditions = [['attribute' => 'age', 'operator' => 'gte', 'value' => 18]];
        expect($model->evaluateConditions(['age' => 18]))->toBeTrue();

        $model->conditions = [['attribute' => 'age', 'operator' => 'lt', 'value' => 18]];
        expect($model->evaluateConditions(['age' => 15]))->toBeTrue();

        $model->conditions = [['attribute' => 'age', 'operator' => 'lte', 'value' => 18]];
        expect($model->evaluateConditions(['age' => 18]))->toBeTrue();

        $model->conditions = [['attribute' => 'role', 'operator' => 'in', 'value' => ['admin', 'manager']]];
        expect($model->evaluateConditions(['role' => 'admin']))->toBeTrue();
        expect($model->evaluateConditions(['role' => 'user']))->toBeFalse();

        $model->conditions = [['attribute' => 'role', 'operator' => 'not_in', 'value' => ['blocked']]];
        expect($model->evaluateConditions(['role' => 'admin']))->toBeTrue();

        $model->conditions = [['attribute' => 'name', 'operator' => 'contains', 'value' => 'test']];
        expect($model->evaluateConditions(['name' => 'testing']))->toBeTrue();

        $model->conditions = [['attribute' => 'value', 'operator' => 'is_null']];
        expect($model->evaluateConditions(['value' => null]))->toBeTrue();

        $model->conditions = [['attribute' => 'value', 'operator' => 'is_not_null']];
        expect($model->evaluateConditions(['value' => 'something']))->toBeTrue();
    });

    it('has correct casts', function (): void {
        $model = new ScopedPermission;
        $casts = $model->getCasts();

        expect($casts['conditions'])->toBe('array');
        expect($casts['granted_at'])->toBe('datetime');
        expect($casts['expires_at'])->toBe('datetime');
    });

    it('matches conditions alias works', function (): void {
        $model = new ScopedPermission;
        $model->conditions = [];

        expect($model->matchesConditions([]))->toBeTrue();
    });
});

describe('PermissionAuditLog model', function (): void {
    it('uses HasUuids trait', function (): void {
        $model = new PermissionAuditLog;

        expect(in_array(HasUuids::class, class_uses_recursive($model)))->toBeTrue();
    });

    it('has correct fillable attributes', function (): void {
        $model = new PermissionAuditLog;

        expect($model->getFillable())->toContain('event_type')
            ->toContain('severity')
            ->toContain('actor_type')
            ->toContain('actor_id')
            ->toContain('old_value')
            ->toContain('new_value');
    });

    it('gets event type as enum', function (): void {
        $model = new PermissionAuditLog;
        $model->event_type = 'permission.granted';

        expect($model->getEventTypeEnum())->toBe(AuditEventType::PermissionGranted);

        $model->event_type = 'invalid';
        expect($model->getEventTypeEnum())->toBeNull();
    });

    it('gets severity as enum', function (): void {
        $model = new PermissionAuditLog;
        $model->severity = 'high';

        expect($model->getSeverityEnum())->toBe(AuditSeverity::High);

        $model->severity = 'invalid';
        expect($model->getSeverityEnum())->toBe(AuditSeverity::Low);
    });

    it('gets description', function (): void {
        $model = new PermissionAuditLog;
        $model->event_type = 'permission.granted';

        expect($model->getDescription())->toBe('Permission Granted');

        $model->target_name = 'test_permission';
        expect($model->getDescription())->toBe('Permission Granted: test_permission');

        $model->event_type = 'invalid';
        expect($model->getDescription())->toBe('invalid');
    });

    it('gets changes between old and new values', function (): void {
        $model = new PermissionAuditLog;
        $model->old_value = ['name' => 'old', 'status' => 'active'];
        $model->new_value = ['name' => 'new', 'status' => 'active'];

        $changes = $model->getChanges();

        expect($changes)->toHaveKey('name');
        expect($changes['name'])->toBe(['old' => 'old', 'new' => 'new']);
        expect($changes)->not->toHaveKey('status');
    });

    it('checks if high severity', function (): void {
        $model = new PermissionAuditLog;

        $model->severity = 'low';
        expect($model->isHighSeverity())->toBeFalse();

        $model->severity = 'medium';
        expect($model->isHighSeverity())->toBeFalse();

        $model->severity = 'high';
        expect($model->isHighSeverity())->toBeTrue();

        $model->severity = 'critical';
        expect($model->isHighSeverity())->toBeTrue();
    });

    it('has correct casts', function (): void {
        $model = new PermissionAuditLog;
        $casts = $model->getCasts();

        expect($casts['old_value'])->toBe('array');
        expect($casts['new_value'])->toBe('array');
        expect($casts['context'])->toBe('array');
        expect($casts['occurred_at'])->toBe('datetime');
    });
});

describe('PermissionGroup model', function (): void {
    it('uses HasUuids trait', function (): void {
        $model = new PermissionGroup;

        expect(in_array(HasUuids::class, class_uses_recursive($model)))->toBeTrue();
    });

    it('has correct fillable attributes', function (): void {
        $model = new PermissionGroup;

        expect($model->getFillable())->toContain('name')
            ->toContain('slug')
            ->toContain('description')
            ->toContain('parent_id')
            ->toContain('implicit_abilities')
            ->toContain('sort_order')
            ->toContain('is_system');
    });

    it('checks if is root', function (): void {
        $model = new PermissionGroup;

        expect($model->isRoot())->toBeTrue();

        $model->parent_id = 'some-uuid';
        expect($model->isRoot())->toBeFalse();
    });

    it('has correct casts', function (): void {
        $model = new PermissionGroup;
        $casts = $model->getCasts();

        expect($casts['implicit_abilities'])->toBe('array');
        expect($casts['sort_order'])->toBe('integer');
        expect($casts['is_system'])->toBe('boolean');
    });
});

describe('RoleTemplate model', function (): void {
    it('uses HasUuids trait', function (): void {
        $model = new RoleTemplate;

        expect(in_array(HasUuids::class, class_uses_recursive($model)))->toBeTrue();
    });

    it('has correct fillable attributes', function (): void {
        $model = new RoleTemplate;

        expect($model->getFillable())->toContain('name')
            ->toContain('slug')
            ->toContain('description')
            ->toContain('parent_id')
            ->toContain('guard_name')
            ->toContain('default_permissions')
            ->toContain('metadata')
            ->toContain('is_system')
            ->toContain('is_active');
    });

    it('gets all default permissions', function (): void {
        $model = new RoleTemplate;
        $model->default_permissions = ['view', 'create'];

        expect($model->getAllDefaultPermissions())->toBe(['view', 'create']);

        $model->default_permissions = null;
        expect($model->getAllDefaultPermissions())->toBe([]);
    });

    it('checks if is root', function (): void {
        $model = new RoleTemplate;

        expect($model->isRoot())->toBeTrue();

        $model->parent_id = 'some-uuid';
        expect($model->isRoot())->toBeFalse();
    });

    it('has correct casts', function (): void {
        $model = new RoleTemplate;
        $casts = $model->getCasts();

        expect($casts['default_permissions'])->toBe('array');
        expect($casts['metadata'])->toBe('array');
        expect($casts['is_system'])->toBe('boolean');
        expect($casts['is_active'])->toBe('boolean');
    });
});
