<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout\Stages;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\StageResult;
use Throwable;

/**
 * Payment stage for checkout pipeline.
 *
 * Processes payment through configured gateway.
 * Supports various payment flows (redirect, direct, async).
 */
final class PaymentStage implements CheckoutStageInterface
{
    /**
     * @var callable|null
     */
    private $processCallback;

    /**
     * @var callable|null
     */
    private $refundCallback;

    /**
     * Set the callback for processing payment.
     *
     * @param  callable(Cart $cart, array $context): array{success: bool, transaction_id?: string, payment_url?: string, message?: string}  $callback
     */
    public function onProcess(callable $callback): self
    {
        $this->processCallback = $callback;

        return $this;
    }

    /**
     * Set the callback for refunding payment.
     *
     * @param  callable(string $transactionId, int $amountCents): bool  $callback
     */
    public function onRefund(callable $callback): self
    {
        $this->refundCallback = $callback;

        return $this;
    }

    public function getName(): string
    {
        return 'payment';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function shouldExecute(Cart $cart, array $context): bool
    {
        // Skip if cart total is zero (fully discounted)
        if ($cart->getRawTotal() <= 0) {
            return false;
        }

        return $this->processCallback !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function execute(Cart $cart, array $context): StageResult
    {
        if ($this->processCallback === null) {
            return StageResult::success('No payment processing configured');
        }

        try {
            $result = ($this->processCallback)($cart, $context);

            if (! ($result['success'] ?? false)) {
                return StageResult::failure(
                    $result['message'] ?? 'Payment processing failed',
                    ['payment' => $result['message'] ?? 'Unknown error']
                );
            }

            $data = [
                'payment_processed_at' => now()->toIso8601String(),
                'payment_amount_cents' => $cart->getRawTotal(),
            ];

            if (isset($result['transaction_id'])) {
                $data['transaction_id'] = $result['transaction_id'];
            }

            if (isset($result['payment_url'])) {
                $data['payment_url'] = $result['payment_url'];
                $data['requires_redirect'] = true;
            }

            return StageResult::success('Payment processed', $data);
        } catch (Throwable $e) {
            return StageResult::failure(
                'Payment processing failed: '.$e->getMessage(),
                ['payment' => $e->getMessage()]
            );
        }
    }

    public function supportsRollback(): bool
    {
        return $this->refundCallback !== null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function rollback(Cart $cart, array $context): void
    {
        $transactionId = $context['transaction_id'] ?? null;

        if ($transactionId === null || $this->refundCallback === null) {
            return;
        }

        $amountCents = $context['payment_amount_cents'] ?? $cart->getRawTotal();

        try {
            ($this->refundCallback)($transactionId, $amountCents);
        } catch (Throwable) {
            // Log but don't fail rollback
        }
    }
}
