<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class CategoryDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Products by Category';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $pivotTable = config('products.database.tables.category_product', 'category_product');
        $categoriesTable = (new Category)->getTable();
        $productsTable = (new Product)->getTable();

        $query = Category::query();

        $productsSubquery = Product::query()->select('id');

        if ((bool) config('products.features.owner.enabled', true)) {
            $query->withoutOwnerScope();
            $productsSubquery->withoutOwnerScope();

            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('products.features.owner.include_global', false);

            $query->where(function (Builder $where) use ($categoriesTable, $owner, $includeGlobal): void {
                if ($owner === null) {
                    $where->whereNull($categoriesTable . '.owner_type')
                        ->whereNull($categoriesTable . '.owner_id');

                    return;
                }

                if (! $includeGlobal) {
                    $where->where($categoriesTable . '.owner_type', $owner->getMorphClass())
                        ->where($categoriesTable . '.owner_id', $owner->getKey());

                    return;
                }

                $where
                    ->where(function (Builder $ownedOrGlobal) use ($categoriesTable, $owner): void {
                        $ownedOrGlobal
                            ->where($categoriesTable . '.owner_type', $owner->getMorphClass())
                            ->where($categoriesTable . '.owner_id', $owner->getKey())
                            ->orWhere(function (Builder $globalOnly) use ($categoriesTable): void {
                                $globalOnly->whereNull($categoriesTable . '.owner_type')
                                    ->whereNull($categoriesTable . '.owner_id');
                            });
                    });
            });

            $productsSubquery->where(function (Builder $where) use ($productsTable, $owner, $includeGlobal): void {
                if ($owner === null) {
                    $where->whereNull($productsTable . '.owner_type')
                        ->whereNull($productsTable . '.owner_id');

                    return;
                }

                if (! $includeGlobal) {
                    $where->where($productsTable . '.owner_type', $owner->getMorphClass())
                        ->where($productsTable . '.owner_id', $owner->getKey());

                    return;
                }

                $where
                    ->where(function (Builder $ownedOrGlobal) use ($productsTable, $owner): void {
                        $ownedOrGlobal
                            ->where($productsTable . '.owner_type', $owner->getMorphClass())
                            ->where($productsTable . '.owner_id', $owner->getKey())
                            ->orWhere(function (Builder $globalOnly) use ($productsTable): void {
                                $globalOnly->whereNull($productsTable . '.owner_type')
                                    ->whereNull($productsTable . '.owner_id');
                            });
                    });
            });
        }

        $categories = $query
            ->select([
                $categoriesTable . '.id',
                $categoriesTable . '.name',
            ])
            ->join($pivotTable, $categoriesTable . '.id', '=', $pivotTable . '.category_id')
            ->join($productsTable, $productsTable . '.id', '=', $pivotTable . '.product_id')
            ->whereIn($productsTable . '.id', $productsSubquery)
            ->selectRaw('count(' . $productsTable . '.id) as products_count')
            ->groupBy($categoriesTable . '.id', $categoriesTable . '.name')
            ->orderByDesc('products_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Products',
                    'data' => $categories->pluck('products_count')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6',
                        '#8b5cf6',
                        '#ec4899',
                        '#f59e0b',
                        '#10b981',
                        '#06b6d4',
                        '#6366f1',
                        '#f97316',
                        '#14b8a6',
                        '#a855f7',
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
