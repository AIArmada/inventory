<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Concerns;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Checkout;
use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;
use Throwable;

trait PerformsCharges // @phpstan-ignore trait.unused
{
    /**
     * Make a "one off" charge on the customer for the given amount.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws IncompletePayment
     */
    public function charge(int $amount, #[SensitiveParameter] ?string $recurringToken = null, array $options = []): Payment
    {
        $rateLimitKey = 'cashier-chip:charge:' . ($this->chip_id ?? $this->getKey());
        $executed = RateLimiter::attempt(
            key: $rateLimitKey,
            maxAttempts: (int) config('cashier-chip.rate_limits.charges_per_minute', 30),
            callback: fn (): bool => true,
            decaySeconds: 60
        );

        if (! $executed) {
            throw new IncompletePayment(
                new Payment(\AIArmada\Chip\Data\PurchaseData::from(['id' => 'rate_limited', 'status' => 'failed'])),
                'Rate limit exceeded. Please wait before making another charge.'
            );
        }

        $builder = Cashier::chip()->purchase()
            ->currency($this->preferredCurrency());

        // Add the product
        $productName = $options['product_name'] ?? 'One-time charge';
        $builder->addProductCents($productName, $amount);

        // Add customer details
        if ($this->hasChipId()) {
            $builder->clientId($this->chip_id);
        } else {
            $builder->customer(
                email: $this->chipEmail() ?? '',
                fullName: $this->chipName()
            );
        }

        // Add redirect URLs if provided
        if (isset($options['success_url'])) {
            $builder->successUrl($options['success_url']);
        }

        if (isset($options['failure_url'])) {
            $builder->failureUrl($options['failure_url']);
        }

        // Create the purchase
        $purchase = $builder->create();

        // If we have a recurring token, charge it immediately
        if ($recurringToken) {
            $purchase = Cashier::chip()->chargePurchase($purchase->id, $recurringToken);
        }

        $payment = new Payment($purchase);

        if ($recurringToken) {
            $payment->validate();
        }

        return $payment;
    }

    /**
     * Create a new PaymentIntent-like instance (purchase in CHIP terms).
     *
     * @param  array<string, mixed>  $options
     */
    public function pay(int $amount, array $options = []): Payment
    {
        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new Payment for the given payment method types.
     *
     * Note: CHIP doesn't support payment method type filtering like Stripe,
     * but this method is provided for API compatibility.
     *
     * @param  array<string>  $paymentMethods
     * @param  array<string, mixed>  $options
     */
    public function payWith(int $amount, array $paymentMethods, array $options = []): Payment
    {
        // CHIP doesn't filter by payment method types, but we store them in options
        $options['payment_methods'] = $paymentMethods;

        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new Payment instance with a CHIP purchase.
     *
     * @param  array<string, mixed>  $options
     */
    public function createPayment(int $amount, array $options = []): Payment
    {
        $builder = Cashier::chip()->purchase()
            ->currency($options['currency'] ?? $this->preferredCurrency());

        // Add the product
        $productName = $options['product_name'] ?? 'Payment';
        $builder->addProductCents($productName, $amount);

        // Add customer details
        if ($this->hasChipId()) {
            $builder->clientId($this->chip_id);
        } else {
            $builder->customer(
                email: $this->chipEmail() ?? '',
                fullName: $this->chipName()
            );
        }

        // Add redirect URLs if provided
        if (isset($options['success_url'])) {
            $builder->successUrl($options['success_url']);
        }

        if (isset($options['failure_url'])) {
            $builder->failureUrl($options['failure_url']);
        }

        if (isset($options['cancel_url'])) {
            $builder->cancelUrl($options['cancel_url']);
        }

        if (isset($options['webhook_url'])) {
            $builder->webhook($options['webhook_url']);
        }

        // Handle pre-authorization
        if (isset($options['skip_capture']) && $options['skip_capture']) {
            $builder->preAuthorize(true);
        }

        // Force recurring if needed
        if (isset($options['force_recurring']) && $options['force_recurring']) {
            $builder->forceRecurring(true);
        }

        // Create the purchase
        $purchase = $builder->create();

        return new Payment($purchase);
    }

    /**
     * Find a payment (purchase) by ID.
     */
    public function findPayment(string $id): ?Payment
    {
        try {
            $purchase = Cashier::chip()->getPurchase($id);

            return new Payment($purchase);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Charge using a recurring token (for subscription renewals, etc.).
     *
     * @param  array<string, mixed>  $options
     *
     * @throws IncompletePayment
     */
    public function chargeWithRecurringToken(int $amount, #[SensitiveParameter] ?string $recurringToken = null, array $options = []): Payment
    {
        // Use the charge method which already supports recurring tokens
        return $this->charge($amount, $recurringToken, $options);
    }

    /**
     * Refund a customer for a charge.
     *
     * @param  array<string, mixed>  $options
     */
    public function refund(string $purchaseId, ?int $amount = null): PurchaseData
    {
        return Cashier::chip()->refundPurchase($purchaseId, $amount);
    }

    /**
     * Begin a new checkout session.
     *
     * @param  int  $amount  Amount in cents
     * @param  array<string, mixed>  $sessionOptions
     * @param  array<string, mixed>  $customerOptions
     */
    public function checkout(int $amount, array $sessionOptions = [], array $customerOptions = []): Checkout
    {
        return Checkout::customer($this)->create($amount, array_merge($sessionOptions, $customerOptions));
    }

    /**
     * Begin a new checkout session for a "one-off" charge.
     *
     * @param  array<string, mixed>  $sessionOptions
     * @param  array<string, mixed>  $customerOptions
     */
    public function checkoutCharge(
        int $amount,
        string $name,
        int $quantity = 1,
        array $sessionOptions = [],
        array $customerOptions = []
    ): Checkout {
        return Checkout::customer($this)
            ->addProduct($name, $amount, $quantity)
            ->create($amount * max(1, $quantity), array_merge($sessionOptions, $customerOptions));
    }
}
