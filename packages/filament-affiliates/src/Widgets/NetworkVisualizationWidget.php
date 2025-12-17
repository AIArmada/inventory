<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

final class NetworkVisualizationWidget extends Widget
{
    public ?string $affiliateId = null;

    public int $depth = 3;

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament-affiliates::widgets.network-visualization';

    public function mount(?string $affiliateId = null): void
    {
        $this->affiliateId = $affiliateId;
    }

    public function getNetworkData(): array
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        if (! $this->affiliateId) {
            // Get root affiliates (no parent)
            $roots = Affiliate::query()
                ->when(
                    (bool) config('affiliates.owner.enabled', false),
                    fn ($query) => $query->forOwner($owner),
                )
                ->whereNull('parent_affiliate_id')
                ->where('status', AffiliateStatus::Active)
                ->with(['rank'])
                ->withCount(['children', 'conversions'])
                ->limit(10)
                ->get();

            return $roots->map(fn (Affiliate $a) => $this->buildNode($a, 0))->all();
        }

        $affiliate = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->whereKey($this->affiliateId)
            ->with(['rank'])
            ->withCount(['children', 'conversions'])
            ->first();

        if (! $affiliate) {
            return [];
        }

        return [$this->buildNode($affiliate, 0)];
    }

    public function getNetworkStats(): array
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        return [
            'total_affiliates' => Affiliate::query()
                ->when(
                    (bool) config('affiliates.owner.enabled', false),
                    fn ($query) => $query->forOwner($owner),
                )
                ->count(),
            'active_affiliates' => Affiliate::query()
                ->when(
                    (bool) config('affiliates.owner.enabled', false),
                    fn ($query) => $query->forOwner($owner),
                )
                ->where('status', AffiliateStatus::Active)
                ->count(),
            'max_depth' => $this->calculateMaxDepth(),
            'avg_children' => $this->calculateAverageChildren(),
        ];
    }

    private function buildNode(Affiliate $affiliate, int $currentDepth): array
    {
        $children = [];

        if ($currentDepth < $this->depth) {
            $children = $affiliate->children()
                ->where('status', AffiliateStatus::Active)
                ->with(['rank'])
                ->withCount(['children', 'conversions'])
                ->get()
                ->map(fn (Affiliate $child) => $this->buildNode($child, $currentDepth + 1))
                ->all();
        }

        $conversionsCount = is_int($affiliate->getAttribute('conversions_count'))
            ? (int) $affiliate->getAttribute('conversions_count')
            : $affiliate->conversions()->count();

        $childrenCount = is_int($affiliate->getAttribute('children_count'))
            ? (int) $affiliate->getAttribute('children_count')
            : $affiliate->children()->count();

        return [
            'id' => $affiliate->id,
            'name' => $affiliate->name,
            'code' => $affiliate->code,
            'status' => $affiliate->status->value,
            'rank' => $affiliate->rank?->name,
            'conversions' => $conversionsCount,
            'children' => $children,
            'children_count' => $childrenCount,
        ];
    }

    private function calculateMaxDepth(): int
    {
        // Simple approximation - count levels from closure table if available
        return 5; // Default max depth
    }

    private function calculateAverageChildren(): float
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        $affiliatesWithChildren = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->whereHas('children')
            ->withCount('children')
            ->get();

        if ($affiliatesWithChildren->isEmpty()) {
            return 0;
        }

        return round($affiliatesWithChildren->avg('children_count'), 1);
    }
}
