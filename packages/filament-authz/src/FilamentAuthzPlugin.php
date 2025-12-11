<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Support\ResourcePermissionDiscovery;
use AIArmada\FilamentAuthz\Widgets\PermissionStatsWidget;
use AIArmada\FilamentAuthz\Widgets\RecentActivityWidget;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentAuthzPlugin implements Plugin
{
    protected bool $autoDiscoverPermissions = false;

    /**
     * @var array<string>
     */
    protected array $discoveryNamespaces = [];

    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'aiarmada-filament-authz';
    }

    /**
     * Enable automatic permission discovery from resources.
     */
    public function discoverPermissions(bool $enabled = true): static
    {
        $this->autoDiscoverPermissions = $enabled;

        return $this;
    }

    /**
     * Add namespaces to scan for resource permissions.
     *
     * @param  array<string>  $namespaces
     */
    public function discoverPermissionsFrom(array $namespaces): static
    {
        $this->discoveryNamespaces = array_merge($this->discoveryNamespaces, $namespaces);
        $this->autoDiscoverPermissions = true;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [
            RoleResource::class,
            PermissionResource::class,
        ];

        if ((bool) config('filament-authz.enable_user_resource')) {
            $resources[] = UserResource::class;
        }

        $pages = [];
        if ((bool) config('filament-authz.features.permission_explorer')) {
            $pages[] = Pages\PermissionExplorer::class;
        }

        // New enterprise pages
        if ((bool) config('filament-authz.features.permission_matrix', true)) {
            $pages[] = PermissionMatrixPage::class;
        }

        if ((bool) config('filament-authz.features.role_hierarchy', true)) {
            $pages[] = RoleHierarchyPage::class;
        }

        if ((bool) config('filament-authz.audit.enabled', true)) {
            $pages[] = AuditLogPage::class;
        }

        $widgets = [];
        if ((bool) config('filament-authz.features.diff_widget')) {
            $widgets[] = Widgets\PermissionsDiffWidget::class;
        }

        if ((bool) config('filament-authz.features.impersonation_banner')) {
            $widgets[] = Widgets\ImpersonationBannerWidget::class;
        }

        // New enterprise widgets
        if ((bool) config('filament-authz.features.stats_widget', true)) {
            $widgets[] = PermissionStatsWidget::class;
        }

        if ((bool) config('filament-authz.features.hierarchy_widget', true)) {
            $widgets[] = RoleHierarchyWidget::class;
        }

        if ((bool) config('filament-authz.features.activity_widget', true) && config('filament-authz.audit.enabled', true)) {
            $widgets[] = RecentActivityWidget::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets);

        $map = (array) config('filament-authz.panel_guard_map');
        if ((bool) config('filament-authz.features.auto_panel_middleware') && isset($map[$panel->getId()])) {
            $guard = (string) $map[$panel->getId()];
            $panel->authGuard($guard);
            $panel->middleware([
                'web',
                'auth:' . $guard,
                'permission:access ' . $panel->getId(),
            ]);
        }

        if ((bool) config('filament-authz.features.panel_role_authorization')) {
            $panel->authMiddleware([
                AuthorizePanelRoles::class,
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        if ($this->shouldAutoDiscoverPermissions()) {
            $this->runPermissionDiscovery($panel);
        }
    }

    protected function shouldAutoDiscoverPermissions(): bool
    {
        return $this->autoDiscoverPermissions || config('filament-authz.discovery.enabled', false);
    }

    protected function runPermissionDiscovery(Panel $panel): void
    {
        /** @var PermissionRegistry $registry */
        $registry = app(PermissionRegistry::class);

        /** @var ResourcePermissionDiscovery $discovery */
        $discovery = new ResourcePermissionDiscovery($registry);

        // Discover from panel resources
        $discovery->discoverFromPanel($panel);

        // Discover from configured namespaces
        $namespaces = array_merge(
            (array) config('filament-authz.discovery.namespaces.include', []),
            $this->discoveryNamespaces
        );

        if (! empty($namespaces)) {
            $discovery->discoverFromNamespaces($namespaces);
        }

        // Auto-sync to database if enabled
        if (config('filament-authz.discovery.auto_sync', false)) {
            $guard = config('filament-authz.default_guard', 'web');
            $registry->sync($guard);
        }
    }
}
