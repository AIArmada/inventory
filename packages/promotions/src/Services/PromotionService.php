<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Services;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * Service for finding and applying automatic promotions.
 */
final class PromotionService implements PromotionServiceInterface
{
    public function __construct(
        private readonly TargetingEngineInterface $targetingEngine
    ) {}

    /**
     * Get all applicable automatic promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotions(TargetingContext $context): Collection
    {
        return Promotion::query()
            ->active()
            ->automatic()
            ->forOwner()
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(fn (Promotion $promotion): bool => $this->matchesContext($promotion, $context));
    }

    /**
     * Get the best applicable promotion for the given context.
     */
    public function getBestPromotion(TargetingContext $context): ?Promotion
    {
        return $this->getApplicablePromotions($context)->first();
    }

    /**
     * Get all stackable promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getStackablePromotions(TargetingContext $context): Collection
    {
        return $this->getApplicablePromotions($context)
            ->filter(fn (Promotion $promotion): bool => $promotion->is_stackable);
    }

    /**
     * Calculate total discount from applicable promotions.
     *
     * @return array{discount: int, applied: Collection<int, Promotion>}
     */
    public function calculateDiscounts(TargetingContext $context, int $subtotalInCents): array
    {
        $applicablePromotions = $this->getApplicablePromotions($context);

        if ($applicablePromotions->isEmpty()) {
            return [
                'discount' => 0,
                'applied' => collect(),
            ];
        }

        $appliedPromotions = collect();
        $totalDiscount = 0;
        $remainingAmount = $subtotalInCents;
        $hasAppliedNonStackable = false;

        foreach ($applicablePromotions as $promotion) {
            if ($hasAppliedNonStackable && ! $promotion->is_stackable) {
                continue;
            }

            if (! $hasAppliedNonStackable || $promotion->is_stackable) {
                $discount = $promotion->calculateDiscount($remainingAmount);

                if ($discount > 0) {
                    $totalDiscount += $discount;
                    $remainingAmount -= $discount;
                    $appliedPromotions->push($promotion);

                    if (! $promotion->is_stackable) {
                        $hasAppliedNonStackable = true;
                    }
                }
            }
        }

        return [
            'discount' => min($totalDiscount, $subtotalInCents),
            'applied' => $appliedPromotions,
        ];
    }

    /**
     * Check if a promotion matches the given context.
     */
    private function matchesContext(Promotion $promotion, TargetingContext $context): bool
    {
        if (! $promotion->isActive()) {
            return false;
        }

        $conditions = $promotion->conditions;

        if (empty($conditions)) {
            return true;
        }

        return $this->targetingEngine->evaluate($conditions, $context);
    }
}
