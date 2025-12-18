<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

describe('HasPanelAuthz Trait Functionality', function (): void {
    beforeEach(function (): void {
        // Create a test class that uses the trait
        $this->testUser = new class extends Model
        {
            use HasPanelAuthz;

            protected array $testRoles = [];

            protected bool $hasRoleCalled = false;

            protected bool $hasAnyRoleCalled = false;

            public function hasRole(string $role): bool
            {
                $this->hasRoleCalled = true;

                return in_array($role, $this->testRoles);
            }

            public function hasAnyRole(array $roles): bool
            {
                $this->hasAnyRoleCalled = true;

                return count(array_intersect($this->testRoles, $roles)) > 0;
            }

            public function setTestRoles(array $roles): void
            {
                $this->testRoles = $roles;
            }

            public function assignRole(string $role): void
            {
                $this->testRoles[] = $role;
            }
        };
    });

    it('grants access when user is super admin', function (): void {
        // Set the config to match our test role
        config(['filament-authz.super_admin_role' => 'super_admin']);
        $this->testUser->setTestRoles(['super_admin']);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        $result = $this->testUser->canAccessPanel($panel);
        expect($result)->toBeTrue();
    });

    it('denies access when user has no roles', function (): void {
        $this->testUser->setTestRoles([]);

        // Mock config
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles.admin' => []]);
        config(['filament-authz.panel_user_role' => null]);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        $result = $this->testUser->canAccessPanel($panel);
        expect($result)->toBeFalse();
    });

    it('grants access when user has panel user role', function (): void {
        $this->testUser->setTestRoles(['panel_user']);

        // Mock config
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles.admin' => []]);
        config(['filament-authz.panel_user_role' => 'panel_user']);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        $result = $this->testUser->canAccessPanel($panel);
        expect($result)->toBeTrue();
    });

    it('grants access when user has panel-specific role', function (): void {
        $this->testUser->setTestRoles(['admin_role']);

        // Mock config
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_roles.admin' => ['admin_role']]);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');

        $result = $this->testUser->canAccessPanel($panel);
        expect($result)->toBeTrue();
    });

    it('checks hasAnyPanelAccess method', function (): void {
        $this->testUser->setTestRoles([]);

        $result = $this->testUser->hasAnyPanelAccess();
        expect($result)->toBeBool();
    });

    it('returns null for default panel when no access', function (): void {
        // When no panels are accessible, should return null
        $this->testUser->setTestRoles([]);
        config(['filament-authz.super_admin_role' => 'super_admin']);
        config(['filament-authz.panel_user_role' => null]);

        $result = $this->testUser->getDefaultPanel();
        // Either null or a Panel, depending on how Filament is configured
        expect($result)->toBeNull();
    });
});

describe('HasPanelAuthz Boot Method', function (): void {
    it('has bootHasPanelAuthz method', function (): void {
        $class = new class
        {
            use HasPanelAuthz;
        };

        // Check method exists and is static
        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod('bootHasPanelAuthz');
        expect($method->isStatic())->toBeTrue()
            ->and($method->isPublic())->toBeTrue();
    });
});
