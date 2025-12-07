<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout\Stages;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\StageResult;
use Throwable;

/**
 * Reservation stage for checkout pipeline.
 *
 * Reserves inventory for all cart items.
 * Supports rollback by releasing reserved stock.
 */
final class ReservationStage implements CheckoutStageInterface
{
    /**
     * @var callable|null
     */
    private $reserveCallback;

    /**
     * @var callable|null
     */
    private $releaseCallback;

    /**
     * Set the callback for reserving inventory.
     *
     * @param  callable(string $itemId, int $quantity, Cart $cart): bool  $callback
     */
    public function onReserve(callable $callback): self
    {
        $this->reserveCallback = $callback;

        return $this;
    }

    /**
     * Set the callback for releasing inventory.
     *
     * @param  callable(string $itemId, int $quantity, Cart $cart): void  $callback
     */
    public function onRelease(callable $callback): self
    {
        $this->releaseCallback = $callback;

        return $this;
    }

    public function getName(): string
    {
        return 'reservation';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function shouldExecute(Cart $cart, array $context): bool
    {
        // Skip if no reserve callback is set
        return $this->reserveCallback !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function execute(Cart $cart, array $context): StageResult
    {
        if ($this->reserveCallback === null) {
            return StageResult::success('No inventory reservation configured');
        }

        $reservedItems = [];
        $errors = [];

        foreach ($cart->getItems() as $item) {
            try {
                $reserved = ($this->reserveCallback)($item->id, $item->quantity, $cart);

                if (! $reserved) {
                    $errors[$item->id] = "Failed to reserve {$item->quantity} units of '{$item->name}'";

                    break;
                }

                $reservedItems[$item->id] = $item->quantity;
            } catch (Throwable $e) {
                $errors[$item->id] = $e->getMessage();

                break;
            }
        }

        if (! empty($errors)) {
            // Rollback already reserved items
            $this->rollbackReservedItems($cart, $reservedItems);

            return StageResult::failure(
                'Inventory reservation failed',
                $errors
            );
        }

        return StageResult::success('Inventory reserved', [
            'reserved_items' => $reservedItems,
            'reserved_at' => now()->toIso8601String(),
        ]);
    }

    public function supportsRollback(): bool
    {
        return $this->releaseCallback !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function rollback(Cart $cart, array $context): void
    {
        $reservedItems = $context['reserved_items'] ?? [];
        $this->rollbackReservedItems($cart, $reservedItems);
    }

    /**
     * @param  array<string, int>  $reservedItems
     */
    private function rollbackReservedItems(Cart $cart, array $reservedItems): void
    {
        if ($this->releaseCallback === null) {
            return;
        }

        foreach ($reservedItems as $itemId => $quantity) {
            try {
                ($this->releaseCallback)($itemId, $quantity, $cart);
            } catch (Throwable) {
                // Log but continue rollback
            }
        }
    }
}
