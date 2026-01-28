<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations\Payment;

use AIArmada\CashierChip\Cashier;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Chip\Facades\Chip;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Cashier CHIP payment processor.
 *
 * Uses the CashierChip package's Billable trait for customers,
 * falling back to direct CHIP API for guest checkout.
 */
final class CashierChipProcessor implements PaymentProcessorInterface
{
    public function getIdentifier(): string
    {
        return 'cashier-chip';
    }

    public function getName(): string
    {
        return 'Cashier CHIP';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        if (! class_exists(Cashier::class)) {
            return false;
        }

        return config('cashier-chip.brand_id') !== null
            && config('cashier-chip.api_key') !== null;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        try {
            $customer = $session->customer;

            if ($customer instanceof Model && method_exists($customer, 'charge')) {
                return $this->createBillablePayment($customer, $request);
            }

            return $this->createGuestPayment($session, $request);
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): PaymentResult
    {
        try {
            $paymentId = $payload['id'] ?? $payload['purchase']['id'] ?? null;
            $status = $payload['status'] ?? $payload['purchase']['status'] ?? 'unknown';

            $paymentStatus = match ($status) {
                'paid', 'completed' => PaymentStatus::Completed,
                'pending' => PaymentStatus::Pending,
                'failed', 'error' => PaymentStatus::Failed,
                'cancelled', 'expired' => PaymentStatus::Cancelled,
                'refunded' => PaymentStatus::Refunded,
                default => PaymentStatus::Processing,
            };

            return new PaymentResult(
                status: $paymentStatus,
                paymentId: $paymentId,
                transactionId: $payload['transaction_id'] ?? null,
                amount: $payload['amount'] ?? null,
                gatewayResponse: $payload,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage());
        }
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return $session->payment_redirect_url;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        try {
            $refund = Chip::refundPurchase($paymentId, $amount);

            return new PaymentResult(
                status: PaymentStatus::Refunded,
                paymentId: $paymentId,
                amount: $amount,
                message: 'Refund processed successfully',
                gatewayResponse: (array) $refund,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed("Refund failed: {$e->getMessage()}", [], $paymentId);
        }
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        try {
            $purchase = Chip::getPurchase($paymentId);

            $status = match ($purchase->status ?? 'unknown') {
                'paid', 'completed' => PaymentStatus::Completed,
                'pending' => PaymentStatus::Pending,
                'failed', 'error' => PaymentStatus::Failed,
                'cancelled', 'expired' => PaymentStatus::Cancelled,
                'refunded' => PaymentStatus::Refunded,
                default => PaymentStatus::Processing,
            };

            return new PaymentResult(
                status: $status,
                paymentId: $paymentId,
                transactionId: $purchase->reference_generated ?? null,
                amount: $purchase->purchase->total->getAmount(),
                gatewayResponse: (array) $purchase,
            );
        } catch (Throwable $e) {
            return PaymentResult::failed($e->getMessage(), [], $paymentId);
        }
    }

    /**
     * Create payment using Billable customer.
     */
    private function createBillablePayment(Model $customer, PaymentRequest $request): PaymentResult
    {
        /** @phpstan-ignore method.notFound */
        $payment = $customer->charge($request->amount, null, [
            'product_name' => $request->description,
            'success_url' => $request->successUrl,
            'failure_url' => $request->failureUrl,
            'metadata' => $request->metadata,
            'reference' => $request->description,
        ]);

        $checkoutUrl = $payment->purchase->checkout_url ?? null;
        $purchaseId = $payment->purchase->id ?? 'unknown';

        if ($checkoutUrl !== null) {
            return PaymentResult::pending(
                paymentId: $purchaseId,
                redirectUrl: $checkoutUrl,
            );
        }

        return PaymentResult::processing($purchaseId);
    }

    /**
     * Create payment for guest checkout using direct CHIP API.
     */
    private function createGuestPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        $purchase = Chip::createPurchase([
            'products' => [
                [
                    'name' => $request->description ?? "Checkout {$session->id}",
                    'price' => $request->amount,
                    'quantity' => 1,
                ],
            ],
            'currency' => $request->currency,
            'client' => [
                'email' => $request->customerEmail,
                'full_name' => $request->customerName,
                'phone' => $request->customerPhone,
            ],
            'reference' => $session->id,
            'success_redirect' => $request->successUrl,
            'failure_redirect' => $request->failureUrl,
            'cancel_redirect' => $request->cancelUrl,
        ]);

        $checkoutUrl = $purchase->checkout_url ?? null;
        $purchaseId = $purchase->id ?? 'unknown';

        if ($checkoutUrl !== null) {
            return PaymentResult::pending(
                paymentId: $purchaseId,
                redirectUrl: $checkoutUrl,
            );
        }

        return PaymentResult::processing($purchaseId);
    }
}
