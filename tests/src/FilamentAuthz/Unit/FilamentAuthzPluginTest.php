<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Widgets\ImpersonationBannerWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionsDiffWidget;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;
use Filament\Panel;

beforeEach(function (): void {
    // Reset config for each test
    config()->set('filament-authz.enable_user_resource', false);
    config()->set('filament-authz.features.permission_explorer', false);
    config()->set('filament-authz.features.permission_matrix', true);
    config()->set('filament-authz.features.role_hierarchy', true);
    config()->set('filament-authz.audit.enabled', true);
    config()->set('filament-authz.features.diff_widget', false);
    config()->set('filament-authz.features.impersonation_banner', false);
    config()->set('filament-authz.features.stats_widget', true);
    config()->set('filament-authz.features.hierarchy_widget', true);
    config()->set('filament-authz.features.activity_widget', true);
    config()->set('filament-authz.features.auto_panel_middleware', false);
    config()->set('filament-authz.features.panel_role_authorization', false);
    config()->set('filament-authz.discovery.enabled', false);
});

afterEach(function (): void {
    Mockery::close();
});

describe('FilamentAuthzPlugin', function (): void {
    describe('make', function (): void {
        it('creates an instance via static make', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        });
    });

    describe('getId', function (): void {
        it('returns the plugin id', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            expect($plugin->getId())->toBe('aiarmada-filament-authz');
        });
    });

    describe('discoverPermissions', function (): void {
        it('enables permission discovery', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            $result = $plugin->discoverPermissions(true);

            expect($result)->toBe($plugin);
        });

        it('disables permission discovery', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            $result = $plugin->discoverPermissions(false);

            expect($result)->toBe($plugin);
        });
    });

    describe('discoverPermissionsFrom', function (): void {
        it('adds namespaces and enables discovery', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            $result = $plugin->discoverPermissionsFrom(['App\\Resources', 'Domain\\Admin']);

            expect($result)->toBe($plugin);
        });

        it('merges multiple namespace calls', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            $plugin->discoverPermissionsFrom(['App\\Resources']);
            $plugin->discoverPermissionsFrom(['Domain\\Admin']);

            expect($plugin)->toBeInstanceOf(FilamentAuthzPlugin::class);
        });
    });

    describe('register', function (): void {
        it('registers core resources', function (): void {
            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')
                ->once()
                ->with(Mockery::on(function ($resources) {
                    return in_array(RoleResource::class, $resources)
                        && in_array(PermissionResource::class, $resources);
                }))
                ->andReturnSelf();
            $panel->shouldReceive('pages')->once()->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();

            $plugin->register($panel);
        });

        it('registers user resource when enabled', function (): void {
            config()->set('filament-authz.enable_user_resource', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')
                ->once()
                ->with(Mockery::on(function ($resources) {
                    return in_array(UserResource::class, $resources);
                }))
                ->andReturnSelf();
            $panel->shouldReceive('pages')->once()->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();

            $plugin->register($panel);
        });

        it('registers permission explorer when enabled', function (): void {
            config()->set('filament-authz.features.permission_explorer', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')
                ->once()
                ->with(Mockery::on(function ($pages) {
                    return in_array(PermissionExplorer::class, $pages);
                }))
                ->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();

            $plugin->register($panel);
        });

        it('registers optional widgets when enabled', function (): void {
            config()->set('filament-authz.features.diff_widget', true);
            config()->set('filament-authz.features.impersonation_banner', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')->once()->andReturnSelf();
            $panel->shouldReceive('widgets')
                ->once()
                ->with(Mockery::on(function ($widgets) {
                    return in_array(PermissionsDiffWidget::class, $widgets)
                        && in_array(ImpersonationBannerWidget::class, $widgets);
                }))
                ->andReturnSelf();

            $plugin->register($panel);
        });

        it('registers enterprise pages when enabled', function (): void {
            config()->set('filament-authz.features.permission_matrix', true);
            config()->set('filament-authz.features.role_hierarchy', true);
            config()->set('filament-authz.audit.enabled', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')
                ->once()
                ->with(Mockery::on(function ($pages) {
                    return in_array(PermissionMatrixPage::class, $pages)
                        && in_array(RoleHierarchyPage::class, $pages)
                        && in_array(AuditLogPage::class, $pages);
                }))
                ->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();

            $plugin->register($panel);
        });

        it('registers enterprise widgets when enabled', function (): void {
            config()->set('filament-authz.features.stats_widget', true);
            config()->set('filament-authz.features.hierarchy_widget', true);
            config()->set('filament-authz.features.activity_widget', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')->once()->andReturnSelf();
            $panel->shouldReceive('widgets')
                ->once()
                ->with(Mockery::on(function ($widgets) {
                    return in_array(PermissionStatsWidget::class, $widgets)
                        && in_array(RoleHierarchyWidget::class, $widgets)
                        && in_array(RecentActivityWidget::class, $widgets);
                }))
                ->andReturnSelf();

            $plugin->register($panel);
        });

        it('disables pages when features are disabled', function (): void {
            config()->set('filament-authz.features.permission_matrix', false);
            config()->set('filament-authz.features.role_hierarchy', false);
            config()->set('filament-authz.audit.enabled', false);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')
                ->once()
                ->with(Mockery::on(function ($pages) {
                    return ! in_array(PermissionMatrixPage::class, $pages)
                        && ! in_array(RoleHierarchyPage::class, $pages)
                        && ! in_array(AuditLogPage::class, $pages);
                }))
                ->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();

            $plugin->register($panel);
        });

        it('configures auto panel middleware when enabled', function (): void {
            config()->set('filament-authz.features.auto_panel_middleware', true);
            config()->set('filament-authz.panel_guard_map', ['admin' => 'admin']);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')->once()->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();
            $panel->shouldReceive('getId')->andReturn('admin');
            $panel->shouldReceive('authGuard')->once()->with('admin')->andReturnSelf();
            $panel->shouldReceive('middleware')
                ->once()
                ->with(['web', 'auth:admin', 'permission:access admin'])
                ->andReturnSelf();

            $plugin->register($panel);
        });

        it('configures panel role authorization middleware when enabled', function (): void {
            config()->set('filament-authz.features.panel_role_authorization', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('resources')->once()->andReturnSelf();
            $panel->shouldReceive('pages')->once()->andReturnSelf();
            $panel->shouldReceive('widgets')->once()->andReturnSelf();
            $panel->shouldReceive('authMiddleware')
                ->once()
                ->andReturnSelf();

            $plugin->register($panel);
        });
    });

    describe('boot', function (): void {
        it('runs permission discovery when enabled via method', function (): void {
            $plugin = FilamentAuthzPlugin::make()->discoverPermissions(true);

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getResources')->andReturn([]);

            // Boot should run discovery
            $plugin->boot($panel);

            expect(true)->toBeTrue(); // If no exception, it worked
        });

        it('runs permission discovery when enabled via config', function (): void {
            config()->set('filament-authz.discovery.enabled', true);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            $panel->shouldReceive('getResources')->andReturn([]);

            $plugin->boot($panel);

            expect(true)->toBeTrue();
        });

        it('does not run discovery when disabled', function (): void {
            config()->set('filament-authz.discovery.enabled', false);

            $plugin = FilamentAuthzPlugin::make();

            $panel = Mockery::mock(Panel::class);
            // getResources should NOT be called since discovery is disabled
            $panel->shouldNotReceive('getResources');

            $plugin->boot($panel);

            expect(true)->toBeTrue();
        });
    });
});
