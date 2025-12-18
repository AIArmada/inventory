<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function (): void {
    Role::query()->delete();
    User::query()->delete();

    config()->set('filament-authz.features.panel_role_authorization', true);
    config()->set('filament-authz.super_admin_role', 'super-admin');
    config()->set('filament-authz.panel_roles.admin', ['admin', 'manager']);
    config()->set('filament-authz.panel_guard_map', ['admin' => 'web']);

    // Create roles
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    Role::create(['name' => 'manager', 'guard_name' => 'web']);
    Role::create(['name' => 'user', 'guard_name' => 'web']);
});

afterEach(function (): void {
    Mockery::close();
});

describe('AuthorizePanelRoles Middleware', function (): void {
    describe('when no panel is current', function (): void {
        it('passes through when no current panel', function (): void {
            Filament::shouldReceive('getCurrentPanel')->andReturn(null);

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });

    describe('when feature is disabled', function (): void {
        it('passes through when feature is disabled', function (): void {
            config()->set('filament-authz.features.panel_role_authorization', false);

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });

    describe('when user is not authenticated', function (): void {
        it('throws AccessDeniedHttpException', function (): void {
            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            // No user set on request

            $middleware->handle($request, function () {
                return new Response('OK');
            });
        })->throws(AccessDeniedHttpException::class);
    });

    describe('when user is super admin', function (): void {
        it('allows super admin to bypass role check', function (): void {
            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('super-admin');

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });

    describe('when panel has no roles configured', function (): void {
        it('allows access when no roles are configured for panel', function (): void {
            config()->set('filament-authz.panel_roles.other', []);

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('other');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('user');

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/other', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });

    describe('when user has required role', function (): void {
        it('allows user with admin role', function (): void {
            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('admin');

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });

        it('allows user with manager role', function (): void {
            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Manager User',
                'email' => 'manager@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('manager');

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });

    describe('when user does not have required role', function (): void {
        it('throws AccessDeniedHttpException when user lacks role', function (): void {
            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Regular User',
                'email' => 'user@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('user');

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            $request->setUserResolver(fn () => $user);

            $middleware->handle($request, function () {
                return new Response('OK');
            });
        })->throws(AccessDeniedHttpException::class);
    });

    describe('when super admin role is empty', function (): void {
        it('does not bypass when super_admin_role is empty string', function (): void {
            config()->set('filament-authz.super_admin_role', '');

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('admin'); // Still needs proper role

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });

    describe('when guard is not mapped', function (): void {
        it('works without guard map', function (): void {
            config()->set('filament-authz.panel_guard_map', []);

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getId')->andReturn('admin');
            Filament::shouldReceive('getCurrentPanel')->andReturn($panel);

            $user = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->assignRole('admin');

            $middleware = new AuthorizePanelRoles;
            $request = Request::create('/admin', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $middleware->handle($request, function () {
                return new Response('OK');
            });

            expect($response->getContent())->toBe('OK');
        });
    });
});
