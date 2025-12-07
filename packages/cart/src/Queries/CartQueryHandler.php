<?php

declare(strict_types=1);

namespace AIArmada\Cart\Queries;

use AIArmada\Cart\ReadModels\CartReadModel;
use DateTimeInterface;

/**
 * Query handler for cart read operations.
 *
 * Handles all read queries using the CartReadModel.
 * Part of the CQRS pattern - separates read from write operations.
 */
final class CartQueryHandler
{
    public function __construct(
        private readonly CartReadModel $readModel
    ) {}

    /**
     * Handle get cart summary query.
     *
     * @return array<string, mixed>|null
     */
    public function handleGetSummary(GetCartSummaryQuery $query): ?array
    {
        return $this->readModel->getCartSummary($query->cartId);
    }

    /**
     * Handle get abandoned carts query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handleGetAbandoned(GetAbandonedCartsQuery $query): array
    {
        return $this->readModel->getAbandonedCarts(
            $query->olderThan,
            $query->minValueCents,
            $query->limit
        );
    }

    /**
     * Handle search carts query.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function handleSearch(SearchCartsQuery $query): array
    {
        return $this->readModel->searchCarts(
            $query->identifier,
            $query->instance,
            $query->createdAfter,
            $query->createdBefore,
            $query->minItems,
            $query->limit,
            $query->offset
        );
    }

    /**
     * Get cart statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(DateTimeInterface $since): array
    {
        return $this->readModel->getCartStatistics($since);
    }

    /**
     * Get full cart details.
     *
     * @return array<string, mixed>|null
     */
    public function getCartDetails(string $cartId): ?array
    {
        return $this->readModel->getCartDetails($cartId);
    }
}
