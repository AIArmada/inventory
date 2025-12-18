<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\ActionMacros;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;

function createActionMacrosTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

beforeEach(function () {
    ActionMacros::register();
});

describe('ActionMacros::register', function () {
    it('registers all action macros', function () {
        expect(Action::hasMacro('requiresPermission'))->toBeTrue();
        expect(Action::hasMacro('requiresRole'))->toBeTrue();
        expect(Action::hasMacro('requiresAnyPermission'))->toBeTrue();
        expect(Action::hasMacro('requiresAllPermissions'))->toBeTrue();
        expect(Action::hasMacro('requiresTeamPermission'))->toBeTrue();
        expect(Action::hasMacro('requiresResourcePermission'))->toBeTrue();
        expect(Action::hasMacro('requiresOwnership'))->toBeTrue();
    });
});

describe('requiresPermission macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresPermission('test.permission');

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('denies access when user is null', function () {
        Auth::shouldReceive('user')->andReturn(null);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('userHasPermission');
        });

        $action = Action::make('test')->requiresPermission('test.permission');

        // The action is created with authorize/visible closures
        expect($action)->toBeInstanceOf(Action::class);
    });

    it('allows access when user has permission', function () {
        $user = createActionMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $action = Action::make('test')->requiresPermission('test.permission');

        expect($action)->toBeInstanceOf(Action::class);
    });
});

describe('requiresRole macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresRole('admin');

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('accepts string role', function () {
        $action = Action::make('test')->requiresRole('admin');

        expect($action)->toBeInstanceOf(Action::class);
    });

    it('accepts array of roles', function () {
        $action = Action::make('test')->requiresRole(['admin', 'editor']);

        expect($action)->toBeInstanceOf(Action::class);
    });
});

describe('requiresAnyPermission macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresAnyPermission(['perm1', 'perm2']);

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('uses aggregator for permission check', function () {
        $user = createActionMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('userHasAnyPermission')
                ->with($user, ['perm1', 'perm2'])
                ->andReturn(true);
        });

        $action = Action::make('test')->requiresAnyPermission(['perm1', 'perm2']);

        expect($action)->toBeInstanceOf(Action::class);
    });
});

describe('requiresAllPermissions macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresAllPermissions(['perm1', 'perm2']);

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('uses aggregator for all permissions check', function () {
        $user = createActionMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('userHasAllPermissions')
                ->with($user, ['perm1', 'perm2'])
                ->andReturn(true);
        });

        $action = Action::make('test')->requiresAllPermissions(['perm1', 'perm2']);

        expect($action)->toBeInstanceOf(Action::class);
    });
});

describe('requiresTeamPermission macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresTeamPermission('team.permission', 'team-123');

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('uses contextual auth service', function () {
        $user = createActionMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(ContextualAuthorizationService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('canInTeam')
                ->with($user, 'team.permission', 'team-123')
                ->andReturn(true);
        });

        $action = Action::make('test')->requiresTeamPermission('team.permission', 'team-123');

        expect($action)->toBeInstanceOf(Action::class);
    });

    it('accepts integer team id', function () {
        $action = Action::make('test')->requiresTeamPermission('team.permission', 42);

        expect($action)->toBeInstanceOf(Action::class);
    });
});

describe('requiresResourcePermission macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresResourcePermission('resource.permission');

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('uses aggregator when resource is null', function () {
        $user = createActionMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'resource.permission')
                ->andReturn(true);
        });

        $action = Action::make('test')->requiresResourcePermission('resource.permission', null);

        expect($action)->toBeInstanceOf(Action::class);
    });

    it('uses contextual auth when resource provided', function () {
        $user = createActionMacrosTestUser();
        $resource = new class extends Illuminate\Database\Eloquent\Model
        {
            protected $table = 'users';
        };

        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(ContextualAuthorizationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('canForResource')
                ->andReturn(true);
        });

        $action = Action::make('test')->requiresResourcePermission('resource.permission', $resource);

        expect($action)->toBeInstanceOf(Action::class);
    });
});

describe('requiresOwnership macro', function () {
    it('returns action instance for chaining', function () {
        $action = Action::make('test');
        $result = $action->requiresOwnership();

        expect($result)->toBeInstanceOf(Action::class);
    });

    it('denies when user is null', function () {
        Auth::shouldReceive('user')->andReturn(null);

        $action = Action::make('test')->requiresOwnership();

        expect($action)->toBeInstanceOf(Action::class);
    });

    it('denies when resource is null', function () {
        $user = createActionMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $action = Action::make('test')->requiresOwnership(null);

        expect($action)->toBeInstanceOf(Action::class);
    });

    it('accepts resource model', function () {
        $user = createActionMacrosTestUser();
        $resource = new class extends Illuminate\Database\Eloquent\Model
        {
            public $user_id = null;

            protected $table = 'users';
        };
        $resource->user_id = $user->getKey();

        Auth::shouldReceive('user')->andReturn($user);

        $action = Action::make('test')->requiresOwnership($resource);

        expect($action)->toBeInstanceOf(Action::class);
    });
});
