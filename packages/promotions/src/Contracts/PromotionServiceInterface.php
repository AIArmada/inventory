<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Contracts;

use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * Contract for promotion services.
 */
interface PromotionServiceInterface
{
    /**
     * Get all applicable automatic promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotions(TargetingContext $context): Collection;

    /**
     * Get the best applicable promotion for the given context.
     */
    public function getBestPromotion(TargetingContext $context): ?Promotion;

    /**
     * Get all stackable promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getStackablePromotions(TargetingContext $context): Collection;

    /**
     * Calculate total discount from applicable promotions.
     *
     * @return array{discount: int, applied: Collection<int, Promotion>}
     */
    public function calculateDiscounts(TargetingContext $context, int $subtotalInCents): array;
}
