<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Concerns;

use AIArmada\FilamentAuthz\Facades\Authz;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Form components for permission management with tabs.
 *
 * Features:
 * - Tabbed interface (Resources/Pages/Widgets/Custom)
 * - Select-all toggles per section
 * - Configurable grid layout
 * - Searchable permission lists
 * - Badge counts on tabs
 */
trait HasAuthzFormComponents
{
    /**
     * Get all permission tabs for the role form.
     *
     * @return array<Tab>
     */
    public static function getPermissionTabs(): array
    {
        $tabs = [];

        if (config('filament-authz.role_resource.tabs.resources', true)) {
            $tabs[] = static::getResourcesTab();
        }

        if (config('filament-authz.role_resource.tabs.pages', true)) {
            $tabs[] = static::getPagesTab();
        }

        if (config('filament-authz.role_resource.tabs.widgets', true)) {
            $tabs[] = static::getWidgetsTab();
        }

        if (config('filament-authz.role_resource.tabs.custom_permissions', true)) {
            $tabs[] = static::getCustomPermissionsTab();
        }

        return $tabs;
    }

    /**
     * Get the full Tabs component with all permission tabs.
     */
    public static function getAuthzFormComponents(): Tabs
    {
        return Tabs::make('Permissions')
            ->contained()
            ->persistTabInQueryString()
            ->tabs(static::getPermissionTabs());
    }

    protected static function getResourcesTab(): Tab
    {
        $resources = Authz::getResources();
        $count = $resources->sum(fn (array $r): int => count($r['permissions']));

        return Tab::make('resources')
            ->label('Resources')
            ->icon('heroicon-o-cube')
            ->badge($count)
            ->visible($resources->isNotEmpty())
            ->schema([
                static::getSelectAllResourcesToggle($resources),
                Grid::make()
                    ->schema(
                        $resources->map(fn (array $resource): Section => static::getResourceSection($resource))->all()
                    )
                    ->columns(config('filament-authz.role_resource.grid_columns', 2)),
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $resources
     */
    protected static function getSelectAllResourcesToggle(Collection $resources): Toggle
    {
        $allPermissions = $resources->flatMap(fn (array $r): array => array_keys($r['permissions']))->all();

        return Toggle::make('select_all_resources')
            ->label('Select All Resources')
            ->helperText('Toggle all resource permissions at once')
            ->live()
            ->columnSpanFull()
            ->afterStateHydrated(function ($state, callable $set, callable $get) use ($allPermissions): void {
                $current = $get('permissions') ?? [];
                $set('select_all_resources', static::shouldSelectAll($allPermissions, $current));
            })
            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($allPermissions): void {
                $current = $get('permissions') ?? [];
                if ($state) {
                    $set('permissions', array_unique(array_merge($current, $allPermissions)));
                } else {
                    $set('permissions', array_diff($current, $allPermissions));
                }
            })
            ->dehydrated(false);
    }

    /**
     * @param  array{class: class-string, permissions: array<string, string>, label: string}  $resource
     */
    protected static function getResourceSection(array $resource): Section
    {
        $permissions = $resource['permissions'];
        $permissionKeys = array_keys($permissions);
        $sectionKey = 'select_all_' . md5($resource['class']);

        return Section::make($resource['label'])
            ->description(count($permissions) . ' permissions')
            ->icon('heroicon-o-rectangle-stack')
            ->collapsible()
            ->compact()
            ->schema([
                Toggle::make($sectionKey)
                    ->label('Select All')
                    ->live()
                    ->afterStateHydrated(function ($state, callable $set, callable $get) use ($sectionKey, $permissionKeys): void {
                        $current = $get('permissions') ?? [];
                        $set($sectionKey, static::shouldSelectAll($permissionKeys, $current));
                    })
                    ->afterStateUpdated(function ($state, callable $set, callable $get) use ($permissionKeys): void {
                        $current = $get('permissions') ?? [];
                        if ($state) {
                            $set('permissions', array_unique(array_merge($current, $permissionKeys)));
                        } else {
                            $set('permissions', array_diff($current, $permissionKeys));
                        }
                    })
                    ->dehydrated(false),
                CheckboxList::make('permissions')
                    ->label('')
                    ->options($permissions)
                    ->columns(config('filament-authz.role_resource.checkbox_columns', 3))
                    ->bulkToggleable()
                    ->searchable()
                    ->gridDirection('row'),
            ]);
    }

    protected static function getPagesTab(): Tab
    {
        $pages = Authz::getPages();

        return Tab::make('pages')
            ->label('Pages')
            ->icon('heroicon-o-document-text')
            ->badge($pages->count())
            ->visible($pages->isNotEmpty())
            ->schema([
                static::getSelectAllToggle('pages', $pages->pluck('permission')->all()),
                Section::make('Page Permissions')
                    ->description('Control access to individual pages')
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->options(
                                $pages->mapWithKeys(fn (array $p): array => [$p['permission'] => $p['label']])->all()
                            )
                            ->columns(config('filament-authz.role_resource.checkbox_columns', 3))
                            ->bulkToggleable()
                            ->searchable()
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    protected static function getWidgetsTab(): Tab
    {
        $widgets = Authz::getWidgets();

        return Tab::make('widgets')
            ->label('Widgets')
            ->icon('heroicon-o-squares-2x2')
            ->badge($widgets->count())
            ->visible($widgets->isNotEmpty())
            ->schema([
                static::getSelectAllToggle('widgets', $widgets->pluck('permission')->all()),
                Section::make('Widget Permissions')
                    ->description('Control visibility of dashboard widgets')
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->options(
                                $widgets->mapWithKeys(fn (array $w): array => [$w['permission'] => $w['label']])->all()
                            )
                            ->columns(config('filament-authz.role_resource.checkbox_columns', 3))
                            ->bulkToggleable()
                            ->searchable()
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    protected static function getCustomPermissionsTab(): Tab
    {
        $custom = Authz::getCustomPermissions();

        return Tab::make('custom')
            ->label('Custom')
            ->icon('heroicon-o-cog-6-tooth')
            ->badge(count($custom))
            ->visible(! empty($custom))
            ->schema([
                static::getSelectAllToggle('custom', array_keys($custom)),
                Section::make('Custom Permissions')
                    ->description('Additional application-specific permissions')
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->options($custom)
                            ->columns(config('filament-authz.role_resource.checkbox_columns', 3))
                            ->bulkToggleable()
                            ->searchable()
                            ->gridDirection('row'),
                    ]),
            ]);
    }

    /**
     * Create a select-all toggle for a permission category.
     *
     * @param  list<string>  $permissionKeys
     */
    protected static function getSelectAllToggle(string $category, array $permissionKeys): Toggle
    {
        return Toggle::make('select_all_' . $category)
            ->label('Select All ' . ucfirst($category))
            ->helperText('Toggle all ' . $category . ' permissions at once')
            ->live()
            ->columnSpanFull()
            ->afterStateHydrated(function ($state, callable $set, callable $get) use ($category, $permissionKeys): void {
                $current = $get('permissions') ?? [];
                $set('select_all_' . $category, static::shouldSelectAll($permissionKeys, $current));
            })
            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($permissionKeys): void {
                $current = $get('permissions') ?? [];
                if ($state) {
                    $set('permissions', array_unique(array_merge($current, $permissionKeys)));
                } else {
                    $set('permissions', array_diff($current, $permissionKeys));
                }
            })
            ->dehydrated(false);
    }

    /**
     * Set permission state from record for edit/view operations.
     */
    public static function setPermissionStateForRecord(
        CheckboxList $component,
        string $operation,
        ?Model $record
    ): void {
        if (! in_array($operation, ['edit', 'view'], true) || $record === null) {
            return;
        }

        if (! method_exists($record, 'permissions')) {
            return;
        }

        $permissionNames = $record->permissions()->pluck('name')->toArray();
        $component->state($permissionNames);
    }

    /**
     * @param  list<string>  $permissionKeys
     * @param  list<string>  $selected
     */
    protected static function shouldSelectAll(array $permissionKeys, array $selected): bool
    {
        if ($permissionKeys === []) {
            return false;
        }

        return array_diff($permissionKeys, $selected) === [];
    }
}
