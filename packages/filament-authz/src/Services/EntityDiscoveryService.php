<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Services\Discovery\PageTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\ResourceTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\WidgetTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class EntityDiscoveryService
{
    protected ResourceTransformer $resourceTransformer;

    protected PageTransformer $pageTransformer;

    protected WidgetTransformer $widgetTransformer;

    /**
     * @var array<string, Collection<int, DiscoveredResource>>
     */
    protected array $resourceCache = [];

    /**
     * @var array<string, Collection<int, DiscoveredPage>>
     */
    protected array $pageCache = [];

    /**
     * @var array<string, Collection<int, DiscoveredWidget>>
     */
    protected array $widgetCache = [];

    public function __construct(
        ?ResourceTransformer $resourceTransformer = null,
        ?PageTransformer $pageTransformer = null,
        ?WidgetTransformer $widgetTransformer = null,
    ) {
        $this->resourceTransformer = $resourceTransformer ?? new ResourceTransformer();
        $this->pageTransformer = $pageTransformer ?? new PageTransformer();
        $this->widgetTransformer = $widgetTransformer ?? new WidgetTransformer();
    }

    /**
     * Discover all resources with optional filtering.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, DiscoveredResource>
     */
    public function discoverResources(array $options = []): Collection
    {
        $cacheKey = 'authz_resources_'.md5(json_encode($options) ?: '');

        if ($this->shouldUseCache() && isset($this->resourceCache[$cacheKey])) {
            return $this->resourceCache[$cacheKey];
        }

        $resources = $this->collectResources($options);

        $transformed = $resources
            ->map(fn (string $resource, string $panel) => $this->transformResource($resource, $panel))
            ->filter(fn (?DiscoveredResource $resource) => $resource !== null && $this->shouldInclude($resource, $options))
            ->values();

        if ($this->shouldUseCache()) {
            $this->resourceCache[$cacheKey] = $transformed;
            Cache::put($cacheKey, $transformed, $this->getCacheTtl());
        }

        return $transformed;
    }

    /**
     * Discover all pages with optional filtering.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, DiscoveredPage>
     */
    public function discoverPages(array $options = []): Collection
    {
        $cacheKey = 'authz_pages_'.md5(json_encode($options) ?: '');

        if ($this->shouldUseCache() && isset($this->pageCache[$cacheKey])) {
            return $this->pageCache[$cacheKey];
        }

        $pages = $this->collectPages($options);

        $transformed = $pages
            ->map(fn (string $page, string $panel) => $this->transformPage($page, $panel))
            ->filter(fn (?DiscoveredPage $page) => $page !== null && $this->shouldIncludePage($page, $options))
            ->values();

        if ($this->shouldUseCache()) {
            $this->pageCache[$cacheKey] = $transformed;
            Cache::put($cacheKey, $transformed, $this->getCacheTtl());
        }

        return $transformed;
    }

    /**
     * Discover all widgets with optional filtering.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, DiscoveredWidget>
     */
    public function discoverWidgets(array $options = []): Collection
    {
        $cacheKey = 'authz_widgets_'.md5(json_encode($options) ?: '');

        if ($this->shouldUseCache() && isset($this->widgetCache[$cacheKey])) {
            return $this->widgetCache[$cacheKey];
        }

        $widgets = $this->collectWidgets($options);

        $transformed = $widgets
            ->map(fn (string $widget, string $panel) => $this->transformWidget($widget, $panel))
            ->filter(fn (?DiscoveredWidget $widget) => $widget !== null && $this->shouldIncludeWidget($widget, $options))
            ->values();

        if ($this->shouldUseCache()) {
            $this->widgetCache[$cacheKey] = $transformed;
            Cache::put($cacheKey, $transformed, $this->getCacheTtl());
        }

        return $transformed;
    }

    /**
     * Discover all entities.
     *
     * @param  array<string, mixed>  $options
     * @return array{resources: Collection<int, DiscoveredResource>, pages: Collection<int, DiscoveredPage>, widgets: Collection<int, DiscoveredWidget>}
     */
    public function discoverAll(array $options = []): array
    {
        return [
            'resources' => $this->discoverResources($options),
            'pages' => $this->discoverPages($options),
            'widgets' => $this->discoverWidgets($options),
        ];
    }

    /**
     * Get all discovered permissions.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, string>
     */
    public function getDiscoveredPermissions(array $options = []): Collection
    {
        $permissions = collect();

        // Resource permissions
        foreach ($this->discoverResources($options) as $resource) {
            $permissions = $permissions->merge($resource->toPermissionKeys());
        }

        // Page permissions
        foreach ($this->discoverPages($options) as $page) {
            $permissions->push($page->getPermissionKey());
        }

        // Widget permissions
        foreach ($this->discoverWidgets($options) as $widget) {
            $permissions->push($widget->getPermissionKey());
        }

        return $permissions->unique()->values();
    }

    /**
     * Warm the discovery cache.
     */
    public function warmCache(): void
    {
        $this->clearCache();

        $this->discoverResources();
        $this->discoverPages();
        $this->discoverWidgets();
    }

    /**
     * Clear the discovery cache.
     */
    public function clearCache(): void
    {
        $this->resourceCache = [];
        $this->pageCache = [];
        $this->widgetCache = [];

        // Clear stored cache
        Cache::forget('authz_resources_'.md5('[]'));
        Cache::forget('authz_pages_'.md5('[]'));
        Cache::forget('authz_widgets_'.md5('[]'));
    }

    /**
     * Collect all resources from panels.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<string, string>
     */
    protected function collectResources(array $options = []): Collection
    {
        $discoverAllPanels = config('filament-authz.discovery.discover_all_panels', true);
        $specificPanels = $options['panels'] ?? config('filament-authz.discovery.panels', []);

        $resources = collect();

        if ($discoverAllPanels || empty($specificPanels)) {
            foreach (Filament::getPanels() as $panelId => $panel) {
                if ($this->shouldDiscoverPanel($panelId, $specificPanels)) {
                    foreach ($panel->getResources() as $resource) {
                        $resources->put($resource, $panelId);
                    }
                }
            }
        } else {
            foreach ($specificPanels as $panelId) {
                $panel = Filament::getPanel($panelId);
                if ($panel) {
                    foreach ($panel->getResources() as $resource) {
                        $resources->put($resource, $panelId);
                    }
                }
            }
        }

        return $resources->unique(fn ($panel, $resource) => $resource);
    }

    /**
     * Collect all pages from panels.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<string, string>
     */
    protected function collectPages(array $options = []): Collection
    {
        $discoverAllPanels = config('filament-authz.discovery.discover_all_panels', true);
        $specificPanels = $options['panels'] ?? config('filament-authz.discovery.panels', []);

        $pages = collect();

        if ($discoverAllPanels || empty($specificPanels)) {
            foreach (Filament::getPanels() as $panelId => $panel) {
                if ($this->shouldDiscoverPanel($panelId, $specificPanels)) {
                    foreach ($panel->getPages() as $page) {
                        $pages->put($page, $panelId);
                    }
                }
            }
        } else {
            foreach ($specificPanels as $panelId) {
                $panel = Filament::getPanel($panelId);
                if ($panel) {
                    foreach ($panel->getPages() as $page) {
                        $pages->put($page, $panelId);
                    }
                }
            }
        }

        return $pages->unique(fn ($panel, $page) => $page);
    }

    /**
     * Collect all widgets from panels.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<string, string>
     */
    protected function collectWidgets(array $options = []): Collection
    {
        $discoverAllPanels = config('filament-authz.discovery.discover_all_panels', true);
        $specificPanels = $options['panels'] ?? config('filament-authz.discovery.panels', []);

        $widgets = collect();

        if ($discoverAllPanels || empty($specificPanels)) {
            foreach (Filament::getPanels() as $panelId => $panel) {
                if ($this->shouldDiscoverPanel($panelId, $specificPanels)) {
                    foreach ($panel->getWidgets() as $widget) {
                        $widgets->put($widget, $panelId);
                    }
                }
            }
        } else {
            foreach ($specificPanels as $panelId) {
                $panel = Filament::getPanel($panelId);
                if ($panel) {
                    foreach ($panel->getWidgets() as $widget) {
                        $widgets->put($widget, $panelId);
                    }
                }
            }
        }

        return $widgets->unique(fn ($panel, $widget) => $widget);
    }

    protected function transformResource(string $resourceClass, string $panel): ?DiscoveredResource
    {
        try {
            return $this->resourceTransformer->transform($resourceClass, $panel);
        } catch (Throwable) {
            return null;
        }
    }

    protected function transformPage(string $pageClass, string $panel): ?DiscoveredPage
    {
        try {
            return $this->pageTransformer->transform($pageClass, $panel);
        } catch (Throwable) {
            return null;
        }
    }

    protected function transformWidget(string $widgetClass, string $panel): ?DiscoveredWidget
    {
        try {
            return $this->widgetTransformer->transform($widgetClass, $panel);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check if a resource should be included based on options.
     *
     * @param  array<string, mixed>  $options
     */
    protected function shouldInclude(DiscoveredResource $resource, array $options): bool
    {
        // Check exclusion list
        $excludeList = $options['exclude'] ?? config('filament-authz.discovery.exclude', []);
        if (in_array($resource->fqcn, $excludeList)) {
            return false;
        }

        // Check namespace patterns
        $includeNamespaces = config('filament-authz.discovery.namespaces.include', []);
        $excludeNamespaces = config('filament-authz.discovery.namespaces.exclude', []);

        if (! empty($includeNamespaces)) {
            $included = false;
            foreach ($includeNamespaces as $pattern) {
                if (Str::is($pattern, $resource->fqcn)) {
                    $included = true;
                    break;
                }
            }
            if (! $included) {
                return false;
            }
        }

        foreach ($excludeNamespaces as $pattern) {
            if (Str::is($pattern, $resource->fqcn)) {
                return false;
            }
        }

        // Check exclude patterns
        $excludePatterns = config('filament-authz.discovery.exclude_patterns', []);
        foreach ($excludePatterns as $pattern) {
            if (Str::is($pattern, class_basename($resource->fqcn))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a page should be included.
     *
     * @param  array<string, mixed>  $options
     */
    protected function shouldIncludePage(DiscoveredPage $page, array $options): bool
    {
        // Exclude default Dashboard if configured
        $excludeList = $options['exclude'] ?? config('filament-authz.discovery.exclude', []);
        if (in_array($page->fqcn, $excludeList)) {
            return false;
        }

        // Always exclude base Dashboard class
        if ($page->fqcn === Dashboard::class) {
            return false;
        }

        return true;
    }

    /**
     * Check if a widget should be included.
     *
     * @param  array<string, mixed>  $options
     */
    protected function shouldIncludeWidget(DiscoveredWidget $widget, array $options): bool
    {
        // Exclude Filament's default widgets
        $defaultExclusions = [
            AccountWidget::class,
            FilamentInfoWidget::class,
        ];

        $excludeList = array_merge(
            $defaultExclusions,
            $options['exclude'] ?? config('filament-authz.discovery.exclude', [])
        );

        return ! in_array($widget->fqcn, $excludeList);
    }

    /**
     * @param  array<string>  $specificPanels
     */
    protected function shouldDiscoverPanel(string $panelId, array $specificPanels): bool
    {
        if (empty($specificPanels)) {
            return true;
        }

        return in_array($panelId, $specificPanels);
    }

    protected function shouldUseCache(): bool
    {
        return config('filament-authz.discovery.cache.enabled', true);
    }

    protected function getCacheTtl(): int
    {
        return config('filament-authz.discovery.cache.ttl', 3600);
    }
}
