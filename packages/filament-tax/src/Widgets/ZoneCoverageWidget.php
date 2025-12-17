<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class ZoneCoverageWidget extends Widget
{
    protected string $view = 'filament-tax::widgets.zone-coverage';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $zones = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
            ->with('rates')
            ->active()
            ->orderBy('priority', 'desc')
            ->get();

        return [
            'zones' => $this->formatZones($zones),
        ];
    }

    /**
     * @param  Collection<int, TaxZone>  $zones
     * @return Collection<int, array<string, mixed>>
     */
    protected function formatZones(Collection $zones): Collection
    {
        return $zones->map(fn (TaxZone $zone): array => [
            'id' => $zone->id,
            'name' => $zone->name,
            'code' => $zone->code,
            'type' => ucfirst($zone->type),
            'countries' => $zone->countries ?? [],
            'states' => $zone->states ?? [],
            'priority' => $zone->priority,
            'is_default' => $zone->is_default,
            'rates' => $zone->rates->map(fn ($rate): array => [
                'name' => $rate->name,
                'class' => ucfirst($rate->tax_class),
                'rate' => number_format($rate->rate / 100, 2) . '%',
                'is_compound' => $rate->is_compound,
            ])->toArray(),
            'rate_count' => $zone->rates->count(),
        ]);
    }
}
