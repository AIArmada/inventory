<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Listeners\PermissionEventSubscriber;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    User::query()->delete();
    Permission::query()->delete();
    Role::query()->delete();

    $this->auditLogger = Mockery::mock(AuditLogger::class);
    $this->subscriber = new PermissionEventSubscriber($this->auditLogger);
});

afterEach(function (): void {
    Mockery::close();
});

function createSubscriberTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

describe('PermissionEventSubscriber', function (): void {
    describe('subscribe', function (): void {
        it('registers all event listeners', function (): void {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $dispatcher->shouldReceive('listen')
                ->with(Login::class, [$this->subscriber, 'handleLogin'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with(Logout::class, [$this->subscriber, 'handleLogout'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with(Failed::class, [$this->subscriber, 'handleFailedLogin'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with(PasswordReset::class, [$this->subscriber, 'handlePasswordReset'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\RoleCreated', [$this->subscriber, 'handleRoleCreated'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\RoleDeleted', [$this->subscriber, 'handleRoleDeleted'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\PermissionCreated', [$this->subscriber, 'handlePermissionCreated'])
                ->once();

            $dispatcher->shouldReceive('listen')
                ->with('Spatie\\Permission\\Events\\PermissionDeleted', [$this->subscriber, 'handlePermissionDeleted'])
                ->once();

            $this->subscriber->subscribe($dispatcher);
        });
    });

    describe('handleLogin', function (): void {
        it('logs user login when user is a model', function (): void {
            $user = createSubscriberTestUser();
            $event = new Login('web', $user, false);

            $this->auditLogger->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject = null, $metadata = null, $severity = null) use ($user) {
                    return $eventType === AuditEventType::UserLogin && $subject === $user;
                });

            $this->subscriber->handleLogin($event);
        });

        it('does not log when user is not a model', function (): void {
            $user = new stdClass;
            $event = new Login('web', $user, false);

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handleLogin($event);
        });
    });

    describe('handleLogout', function (): void {
        it('logs user logout when user is a model', function (): void {
            $user = createSubscriberTestUser();
            $event = new Logout('web', $user);

            $this->auditLogger->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject = null) use ($user) {
                    return $eventType === AuditEventType::UserLogout && $subject === $user;
                });

            $this->subscriber->handleLogout($event);
        });

        it('does not log when user is not a model', function (): void {
            $user = new stdClass;
            $event = new Logout('web', $user);

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handleLogout($event);
        });
    });

    describe('handleFailedLogin', function (): void {
        it('logs failed login attempt with credentials', function (): void {
            $credentials = ['email' => 'test@example.com', 'password' => 'secret'];
            $event = new Failed('web', null, $credentials);

            // The log method signature is: (eventType, subject, target, oldValues, newValues, metadata, severity)
            $this->auditLogger->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject, $target, $oldValues, $newValues, $metadata, $severity) use ($credentials) {
                    return $eventType === AuditEventType::LoginFailed
                        && $metadata === ['credentials' => $credentials]
                        && $severity === AuditSeverity::Medium;
                });

            $this->subscriber->handleFailedLogin($event);
        });
    });

    describe('handlePasswordReset', function (): void {
        it('logs password reset when user is a model', function (): void {
            $user = createSubscriberTestUser();
            $event = new PasswordReset($user);

            $this->auditLogger->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject = null) use ($user) {
                    return $eventType === AuditEventType::PasswordChanged && $subject === $user;
                });

            $this->subscriber->handlePasswordReset($event);
        });

        it('does not log when user is not a model', function (): void {
            $user = new stdClass;
            $event = new PasswordReset($user);

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePasswordReset($event);
        });
    });

    describe('handleRoleCreated', function (): void {
        it('logs role creation when event has role model', function (): void {
            $role = Role::create(['name' => 'new-role', 'guard_name' => 'web']);
            $event = new stdClass;
            $event->role = $role;

            $this->auditLogger->shouldReceive('logRoleCreated')
                ->once()
                ->with($role);

            $this->subscriber->handleRoleCreated($event);
        });

        it('does not log when event has no role property', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('logRoleCreated');

            $this->subscriber->handleRoleCreated($event);
        });

        it('does not log when role is not a model', function (): void {
            $event = new stdClass;
            $event->role = 'not-a-model';

            $this->auditLogger->shouldNotReceive('logRoleCreated');

            $this->subscriber->handleRoleCreated($event);
        });
    });

    describe('handleRoleDeleted', function (): void {
        it('logs role deletion when event has role model', function (): void {
            $role = Role::create(['name' => 'deleted-role', 'guard_name' => 'web']);
            $event = new stdClass;
            $event->role = $role;

            $this->auditLogger->shouldReceive('logRoleDeleted')
                ->once()
                ->with($role);

            $this->subscriber->handleRoleDeleted($event);
        });

        it('does not log when event has no role property', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('logRoleDeleted');

            $this->subscriber->handleRoleDeleted($event);
        });

        it('does not log when role is not a model', function (): void {
            $event = new stdClass;
            $event->role = ['name' => 'array-role'];

            $this->auditLogger->shouldNotReceive('logRoleDeleted');

            $this->subscriber->handleRoleDeleted($event);
        });
    });

    describe('handlePermissionCreated', function (): void {
        it('logs permission creation when event has permission model', function (): void {
            $permission = Permission::create(['name' => 'new.permission', 'guard_name' => 'web']);
            $event = new stdClass;
            $event->permission = $permission;

            $this->auditLogger->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject = null) use ($permission) {
                    return $eventType === AuditEventType::PermissionCreated && $subject === $permission;
                });

            $this->subscriber->handlePermissionCreated($event);
        });

        it('does not log when event has no permission property', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePermissionCreated($event);
        });

        it('does not log when permission is not a model', function (): void {
            $event = new stdClass;
            $event->permission = 'string-permission';

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePermissionCreated($event);
        });
    });

    describe('handlePermissionDeleted', function (): void {
        it('logs permission deletion when event has permission model', function (): void {
            $permission = Permission::create(['name' => 'deleted.permission', 'guard_name' => 'web']);
            $event = new stdClass;
            $event->permission = $permission;

            $this->auditLogger->shouldReceive('log')
                ->once()
                ->withArgs(function ($eventType, $subject = null) use ($permission) {
                    return $eventType === AuditEventType::PermissionDeleted && $subject === $permission;
                });

            $this->subscriber->handlePermissionDeleted($event);
        });

        it('does not log when event has no permission property', function (): void {
            $event = new stdClass;

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePermissionDeleted($event);
        });

        it('does not log when permission is not a model', function (): void {
            $event = new stdClass;
            $event->permission = 12345;

            $this->auditLogger->shouldNotReceive('log');

            $this->subscriber->handlePermissionDeleted($event);
        });
    });
});
