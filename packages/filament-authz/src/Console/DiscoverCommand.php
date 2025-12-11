<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class DiscoverCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'authz:discover
        {--panel= : Discover from specific panel only}
        {--type= : Entity type (resources, pages, widgets, all)}
        {--generate : Generate permissions for discovered entities}
        {--format=table : Output format (table, json)}';

    /**
     * @var string
     */
    protected $description = 'Discover and display all Filament entities with their permissions';

    public function handle(EntityDiscoveryService $discovery): int
    {
        $this->info("🔍 Discovering Filament Entities...\n");

        $options = [];
        if ($panel = $this->option('panel')) {
            $options['panels'] = [$panel];
        }

        $type = $this->option('type') ?? 'all';

        $resources = collect();
        $pages = collect();
        $widgets = collect();

        if ($type === 'all' || $type === 'resources') {
            $resources = $discovery->discoverResources($options);
        }

        if ($type === 'all' || $type === 'pages') {
            $pages = $discovery->discoverPages($options);
        }

        if ($type === 'all' || $type === 'widgets') {
            $widgets = $discovery->discoverWidgets($options);
        }

        if ($this->option('format') === 'json') {
            $this->outputJson($resources, $pages, $widgets);

            return Command::SUCCESS;
        }

        $this->displayTable('Resources', $resources);
        $this->displayTable('Pages', $pages);
        $this->displayTable('Widgets', $widgets);

        $this->newLine();
        $this->info('📊 Summary:');

        $resourcePermissionCount = $resources->sum(fn ($r) => count($r->permissions));

        $this->table(['Entity Type', 'Count', 'Permissions'], [
            ['Resources', $resources->count(), $resourcePermissionCount],
            ['Pages', $pages->count(), $pages->count()],
            ['Widgets', $widgets->count(), $widgets->count()],
            ['<fg=cyan>Total</>', $resources->count() + $pages->count() + $widgets->count(), $resourcePermissionCount + $pages->count() + $widgets->count()],
        ]);

        if ($this->option('generate')) {
            $this->generatePermissions($resources, $pages, $widgets);
        }

        return Command::SUCCESS;
    }

    /**
     * @param  Collection<int, DiscoveredResource|DiscoveredPage|DiscoveredWidget>  $items
     */
    protected function displayTable(string $title, Collection $items): void
    {
        if ($items->isEmpty()) {
            $this->line("\n<fg=yellow>{$title}:</> None found\n");

            return;
        }

        $this->line("\n<fg=cyan>{$title}:</>\n");

        $rows = $items->map(function ($item) use ($title) {
            if ($title === 'Resources') {
                return [
                    class_basename($item->fqcn),
                    $item->model,
                    $item->panel ?? '-',
                    implode(', ', array_slice($item->permissions, 0, 3)) . (count($item->permissions) > 3 ? '...' : ''),
                ];
            }

            if ($title === 'Pages') {
                return [
                    class_basename($item->fqcn),
                    $item->title ?? '-',
                    $item->panel ?? '-',
                    $item->getPermissionKey(),
                ];
            }

            return [
                class_basename($item->fqcn),
                $item->type ?? 'custom',
                $item->panel ?? '-',
                $item->getPermissionKey(),
            ];
        })->toArray();

        $headers = $title === 'Resources'
            ? ['Resource', 'Model', 'Panel', 'Permissions']
            : ($title === 'Pages'
                ? ['Page', 'Title', 'Panel', 'Permission']
                : ['Widget', 'Type', 'Panel', 'Permission']);

        $this->table($headers, $rows);
    }

    /**
     * @param  Collection<int, DiscoveredResource>  $resources
     * @param  Collection<int, DiscoveredPage>  $pages
     * @param  Collection<int, DiscoveredWidget>  $widgets
     */
    protected function outputJson(Collection $resources, Collection $pages, Collection $widgets): void
    {
        $output = [
            'resources' => $resources->map(fn ($r) => $r->toArray())->values()->toArray(),
            'pages' => $pages->map(fn ($p) => $p->toArray())->values()->toArray(),
            'widgets' => $widgets->map(fn ($w) => $w->toArray())->values()->toArray(),
            'summary' => [
                'total_resources' => $resources->count(),
                'total_pages' => $pages->count(),
                'total_widgets' => $widgets->count(),
                'total_permissions' => $resources->sum(fn ($r) => count($r->permissions)) + $pages->count() + $widgets->count(),
            ],
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }

    /**
     * @param  Collection<int, DiscoveredResource>  $resources
     * @param  Collection<int, DiscoveredPage>  $pages
     * @param  Collection<int, DiscoveredWidget>  $widgets
     */
    protected function generatePermissions(Collection $resources, Collection $pages, Collection $widgets): void
    {
        $this->newLine();
        $this->info('🔧 Generating permissions...');

        $count = 0;

        // Generate resource permissions
        foreach ($resources as $resource) {
            foreach ($resource->toPermissionKeys() as $permissionKey) {
                Permission::findOrCreate($permissionKey);
                $count++;
            }
        }

        // Generate page permissions
        foreach ($pages as $page) {
            Permission::findOrCreate($page->getPermissionKey());
            $count++;
        }

        // Generate widget permissions
        foreach ($widgets as $widget) {
            Permission::findOrCreate($widget->getPermissionKey());
            $count++;
        }

        $this->info("✅ Generated {$count} permissions");
    }
}
