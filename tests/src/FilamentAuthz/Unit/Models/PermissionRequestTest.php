<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::query()->delete();
    Role::query()->delete();

    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $this->approver = User::create([
        'name' => 'Approver',
        'email' => 'approver@example.com',
        'password' => 'password',
    ]);

    config(['filament-authz.user_model' => User::class]);
});

describe('PermissionRequest constants', function (): void {
    it('has correct status constants', function (): void {
        expect(PermissionRequest::STATUS_PENDING)->toBe('pending')
            ->and(PermissionRequest::STATUS_APPROVED)->toBe('approved')
            ->and(PermissionRequest::STATUS_DENIED)->toBe('denied')
            ->and(PermissionRequest::STATUS_EXPIRED)->toBe('expired');
    });
});

describe('PermissionRequest getTable', function (): void {
    it('returns table name from config', function (): void {
        $request = new PermissionRequest;
        $table = $request->getTable();

        expect($table)->toBe(config('filament-authz.database.tables.permission_requests', 'authz_permission_requests'));
    });
});

describe('PermissionRequest relationships', function (): void {
    it('belongs to requester', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'justification' => 'Need access',
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        expect($request->requester)->toBeInstanceOf(User::class)
            ->and($request->requester->id)->toBe($this->user->id);
    });

    it('belongs to approver when approved', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'approver_id' => $this->approver->id,
            'requested_permissions' => ['users.view'],
            'justification' => 'Need access',
            'status' => PermissionRequest::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        expect($request->approver)->toBeInstanceOf(User::class)
            ->and($request->approver->id)->toBe($this->approver->id);
    });

    it('has null approver when pending', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'justification' => 'Need access',
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        expect($request->approver)->toBeNull();
    });
});

describe('PermissionRequest status checks', function (): void {
    it('isPending returns true for pending requests', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        expect($request->isPending())->toBeTrue()
            ->and($request->isApproved())->toBeFalse()
            ->and($request->isDenied())->toBeFalse();
    });

    it('isApproved returns true for approved requests', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_APPROVED,
        ]);

        expect($request->isApproved())->toBeTrue()
            ->and($request->isPending())->toBeFalse()
            ->and($request->isDenied())->toBeFalse();
    });

    it('isDenied returns true for denied requests', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_DENIED,
        ]);

        expect($request->isDenied())->toBeTrue()
            ->and($request->isPending())->toBeFalse()
            ->and($request->isApproved())->toBeFalse();
    });
});

describe('PermissionRequest isExpired', function (): void {
    it('returns true for expired status', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_EXPIRED,
        ]);

        expect($request->isExpired())->toBeTrue();
    });

    it('returns true when expires_at is in the past', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
            'expires_at' => now()->subDay(),
        ]);

        expect($request->isExpired())->toBeTrue();
    });

    it('returns false when expires_at is in the future', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
            'expires_at' => now()->addDay(),
        ]);

        expect($request->isExpired())->toBeFalse();
    });

    it('returns false when no expiry set', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        expect($request->isExpired())->toBeFalse();
    });
});

describe('PermissionRequest approve', function (): void {
    it('updates status to approved', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        $request->approve($this->approver, 'Approved for project work');

        $request->refresh();
        expect($request->status)->toBe(PermissionRequest::STATUS_APPROVED)
            ->and((int) $request->approver_id)->toBe($this->approver->id)
            ->and($request->approved_at)->not->toBeNull()
            ->and($request->approver_note)->toBe('Approved for project work');
    });

    it('grants requested permissions to user', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view', 'users.create'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        $request->approve($this->approver);

        $this->user->refresh();
        expect($this->user->hasPermissionTo('users.view'))->toBeTrue()
            ->and($this->user->hasPermissionTo('users.create'))->toBeTrue();
    });

    it('assigns requested roles to user', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_roles' => ['admin'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        $request->approve($this->approver);

        $this->user->refresh();
        expect($this->user->hasRole('admin'))->toBeTrue();
    });
});

describe('PermissionRequest deny', function (): void {
    it('updates status to denied with reason', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        $request->deny($this->approver, 'Not authorized for this resource');

        $request->refresh();
        expect($request->status)->toBe(PermissionRequest::STATUS_DENIED)
            ->and((int) $request->approver_id)->toBe($this->approver->id)
            ->and($request->denied_at)->not->toBeNull()
            ->and($request->denial_reason)->toBe('Not authorized for this resource');
    });

    it('does not grant any permissions', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        $request->deny($this->approver, 'Denied');

        $this->user->refresh();
        expect($this->user->hasPermissionTo('users.view'))->toBeFalse();
    });
});

describe('PermissionRequest casts', function (): void {
    it('casts requested_permissions to array', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view', 'users.create'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        expect($request->requested_permissions)->toBeArray()
            ->and($request->requested_permissions)->toContain('users.view')
            ->and($request->requested_permissions)->toContain('users.create');
    });

    it('casts requested_roles to array', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_roles' => ['admin', 'editor'],
            'status' => PermissionRequest::STATUS_PENDING,
        ]);

        expect($request->requested_roles)->toBeArray()
            ->and($request->requested_roles)->toContain('admin')
            ->and($request->requested_roles)->toContain('editor');
    });

    it('casts dates correctly', function (): void {
        $request = PermissionRequest::create([
            'requester_id' => $this->user->id,
            'requested_permissions' => ['users.view'],
            'status' => PermissionRequest::STATUS_APPROVED,
            'approved_at' => '2024-01-15 10:00:00',
            'expires_at' => '2024-02-15 10:00:00',
        ]);

        expect($request->approved_at)->toBeInstanceOf(Illuminate\Support\Carbon::class)
            ->and($request->expires_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });
});

describe('PermissionRequest fillable', function (): void {
    it('has correct fillable attributes', function (): void {
        $request = new PermissionRequest;
        $fillable = $request->getFillable();

        expect($fillable)->toContain('requester_id')
            ->toContain('approver_id')
            ->toContain('requested_permissions')
            ->toContain('requested_roles')
            ->toContain('justification')
            ->toContain('status')
            ->toContain('approved_at')
            ->toContain('denied_at')
            ->toContain('expires_at')
            ->toContain('approver_note')
            ->toContain('denial_reason');
    });
});
