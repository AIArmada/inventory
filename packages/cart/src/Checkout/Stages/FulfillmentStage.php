<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout\Stages;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\StageResult;
use Throwable;

/**
 * Fulfillment stage for checkout pipeline.
 *
 * Creates orders and handles post-payment fulfillment.
 * Converts cart to order and initiates delivery/fulfillment process.
 */
final class FulfillmentStage implements CheckoutStageInterface
{
    /**
     * @var callable|null
     */
    private $createOrderCallback;

    /**
     * @var callable|null
     */
    private $cancelOrderCallback;

    /**
     * Set the callback for creating an order.
     *
     * @param  callable(Cart $cart, array $context): array{order_id: string, order_number?: string}  $callback
     */
    public function onCreateOrder(callable $callback): self
    {
        $this->createOrderCallback = $callback;

        return $this;
    }

    /**
     * Set the callback for canceling an order.
     *
     * @param  callable(string $orderId): void  $callback
     */
    public function onCancelOrder(callable $callback): self
    {
        $this->cancelOrderCallback = $callback;

        return $this;
    }

    public function getName(): string
    {
        return 'fulfillment';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function shouldExecute(Cart $cart, array $context): bool
    {
        return $this->createOrderCallback !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function execute(Cart $cart, array $context): StageResult
    {
        if ($this->createOrderCallback === null) {
            return StageResult::success('No order fulfillment configured');
        }

        try {
            $result = ($this->createOrderCallback)($cart, $context);

            if (! isset($result['order_id'])) {
                return StageResult::failure('Order creation did not return an order ID');
            }

            $data = [
                'order_id' => $result['order_id'],
                'fulfilled_at' => now()->toIso8601String(),
            ];

            if (isset($result['order_number'])) {
                $data['order_number'] = $result['order_number'];
            }

            return StageResult::success('Order created', $data);
        } catch (Throwable $e) {
            return StageResult::failure(
                'Order creation failed: '.$e->getMessage(),
                ['fulfillment' => $e->getMessage()]
            );
        }
    }

    public function supportsRollback(): bool
    {
        return $this->cancelOrderCallback !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function rollback(Cart $cart, array $context): void
    {
        $orderId = $context['order_id'] ?? null;

        if ($orderId === null || $this->cancelOrderCallback === null) {
            return;
        }

        try {
            ($this->cancelOrderCallback)($orderId);
        } catch (Throwable) {
            // Log but don't fail rollback
        }
    }
}
