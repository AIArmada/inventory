<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Concerns\HasOwnerPermissions;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

class TestModelWithOwnerPermissions extends Model
{
    use HasOwnerPermissions;

    public $timestamps = false;

    protected $table = 'authz_test_models';

    protected $guarded = [];
}

beforeEach(function (): void {
    // Create test table
    $this->app['db']->connection()->getSchemaBuilder()->create('authz_test_models', function ($table): void {
        $table->increments('id');
        $table->unsignedInteger('user_id')->nullable();
        $table->string('name')->nullable();
    });

    $this->contextualAuth = Mockery::mock(ContextualAuthorizationService::class);
    $this->app->instance(ContextualAuthorizationService::class, $this->contextualAuth);

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
    $this->model = TestModelWithOwnerPermissions::create(['user_id' => $this->user->id, 'name' => 'Test']);
});

afterEach(function (): void {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('authz_test_models');
});

describe('HasOwnerPermissions isOwnedBy', function (): void {
    it('returns true when model is owned by user', function (): void {
        expect($this->model->isOwnedBy($this->user))->toBeTrue();
    });

    it('returns false when model is not owned by user', function (): void {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => 'password',
        ]);
        expect($this->model->isOwnedBy($otherUser))->toBeFalse();
    });
});

describe('HasOwnerPermissions scopeOwnedBy', function (): void {
    it('scopes to models owned by user', function (): void {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => 'password',
        ]);
        TestModelWithOwnerPermissions::create(['user_id' => $otherUser->id, 'name' => 'Other']);

        $results = TestModelWithOwnerPermissions::ownedBy($this->user)->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->name)->toBe('Test');
    });
});

describe('HasOwnerPermissions canUserPerform', function (): void {
    it('returns true when user has resource permission', function (): void {
        $this->contextualAuth->shouldReceive('canForResource')
            ->with($this->user, 'test_models.view', Mockery::any())
            ->andReturn(true);

        expect($this->model->canUserPerform($this->user, 'view'))->toBeTrue();
    });

    it('returns true when owner has owner-specific permission', function (): void {
        $this->contextualAuth->shouldReceive('canForResource')
            ->andReturn(false);

        $this->contextualAuth->shouldReceive('canWithContext')
            ->with(
                $this->user,
                'test_models.view.own',
                Mockery::on(function ($context): bool {
                    return $context['scope'] === PermissionScope::Owner->value;
                })
            )
            ->andReturn(true);

        expect($this->model->canUserPerform($this->user, 'view'))->toBeTrue();
    });

    it('returns false when user has no permissions', function (): void {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other2@example.com',
            'password' => 'password',
        ]);

        $this->contextualAuth->shouldReceive('canForResource')
            ->andReturn(false);

        expect($this->model->canUserPerform($otherUser, 'view'))->toBeFalse();
    });
});

describe('HasOwnerPermissions scopeViewableBy', function (): void {
    it('returns all when user has viewAny permission', function (): void {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other3@example.com',
            'password' => 'password',
        ]);
        TestModelWithOwnerPermissions::create(['user_id' => $otherUser->id, 'name' => 'Other']);

        $this->contextualAuth->shouldReceive('canWithContext')
            ->with($this->user, 'test_models.viewAny', [])
            ->andReturn(true);

        $results = TestModelWithOwnerPermissions::viewableBy($this->user)->get();

        expect($results)->toHaveCount(2);
    });

    it('returns only owned when user lacks viewAny permission', function (): void {
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other4@example.com',
            'password' => 'password',
        ]);
        TestModelWithOwnerPermissions::create(['user_id' => $otherUser->id, 'name' => 'Other']);

        $this->contextualAuth->shouldReceive('canWithContext')
            ->with($this->user, 'test_models.viewAny', [])
            ->andReturn(false);

        $results = TestModelWithOwnerPermissions::viewableBy($this->user)->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->name)->toBe('Test');
    });
});

describe('HasOwnerPermissions getResourceName', function (): void {
    it('removes authz_ prefix', function (): void {
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('getResourceName');
        $method->setAccessible(true);

        expect($method->invoke($this->model))->toBe('test_models');
    });

    it('removes inv_ prefix', function (): void {
        $model = new class extends Model
        {
            use HasOwnerPermissions;

            protected $table = 'inv_inventory_items';
        };

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getResourceName');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBe('inventory_items');
    });

    it('removes vou_ prefix', function (): void {
        $model = new class extends Model
        {
            use HasOwnerPermissions;

            protected $table = 'vou_vouchers';
        };

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getResourceName');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBe('vouchers');
    });

    it('keeps table name without known prefix', function (): void {
        $model = new class extends Model
        {
            use HasOwnerPermissions;

            protected $table = 'orders';
        };

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getResourceName');
        $method->setAccessible(true);

        expect($method->invoke($model))->toBe('orders');
    });
});

describe('HasOwnerPermissions getOwnerKeyName', function (): void {
    it('defaults to user_id', function (): void {
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('getOwnerKeyName');
        $method->setAccessible(true);

        expect($method->invoke($this->model))->toBe('user_id');
    });
});

describe('HasOwnerPermissions getPermissionName', function (): void {
    it('formats permission name correctly', function (): void {
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('getPermissionName');
        $method->setAccessible(true);

        expect($method->invoke($this->model, 'view'))->toBe('test_models.view')
            ->and($method->invoke($this->model, 'create'))->toBe('test_models.create');
    });
});

describe('HasOwnerPermissions getOwnerPermissionName', function (): void {
    it('formats owner permission name correctly', function (): void {
        $reflection = new ReflectionClass($this->model);
        $method = $reflection->getMethod('getOwnerPermissionName');
        $method->setAccessible(true);

        expect($method->invoke($this->model, 'view'))->toBe('test_models.view.own')
            ->and($method->invoke($this->model, 'update'))->toBe('test_models.update.own');
    });
});
