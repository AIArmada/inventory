<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\SubscriptionBuilderContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\CashierChip\SubscriptionBuilder;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Wrapper for CHIP subscription builder.
 *
 * This class wraps the CashierChip SubscriptionBuilder and adapts it
 * to the unified SubscriptionBuilderContract interface.
 */
class ChipSubscriptionBuilder implements SubscriptionBuilderContract
{
    /**
     * The underlying subscription builder.
     */
    protected SubscriptionBuilder $builder;

    /**
     * Create a new CHIP subscription builder.
     *
     * @param  string|array<string>  $prices
     */
    public function __construct(
        protected BillableContract $billable,
        protected string $type,
        string | array $prices = []
    ) {
        // Directly instantiate the native SubscriptionBuilder to avoid
        // conflicts with the unified cashier package's method overrides
        $this->builder = new SubscriptionBuilder($billable, $type, $prices);
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
    }

    /**
     * Set the price for the subscription.
     */
    public function price(string | array $price, ?int $quantity = 1): self
    {
        $this->builder->price($price, $quantity);

        return $this;
    }

    /**
     * Set the quantity of the subscription.
     */
    public function quantity(?int $quantity, ?string $price = null): self
    {
        $this->builder->quantity($quantity, $price);

        return $this;
    }

    /**
     * Set the trial days.
     */
    public function trialDays(int $trialDays): self
    {
        $this->builder->trialDays($trialDays);

        return $this;
    }

    /**
     * Set the trial end date.
     */
    public function trialUntil(CarbonInterface $trialUntil): self
    {
        $this->builder->trialUntil($trialUntil);

        return $this;
    }

    /**
     * Skip the trial period.
     */
    public function skipTrial(): self
    {
        $this->builder->skipTrial();

        return $this;
    }

    /**
     * Set the billing interval.
     */
    public function billingInterval(string $interval, int $count = 1): self
    {
        $this->builder->billingInterval($interval, $count);

        return $this;
    }

    /**
     * Set monthly billing.
     */
    public function monthly(int $count = 1): self
    {
        $this->builder->monthly($count);

        return $this;
    }

    /**
     * Set yearly billing.
     */
    public function yearly(int $count = 1): self
    {
        $this->builder->yearly($count);

        return $this;
    }

    /**
     * Set weekly billing.
     */
    public function weekly(int $count = 1): self
    {
        $this->builder->weekly($count);

        return $this;
    }

    /**
     * Set daily billing.
     */
    public function daily(int $count = 1): self
    {
        $this->builder->daily($count);

        return $this;
    }

    /**
     * Anchor the billing cycle to a date.
     */
    public function anchorBillingCycleOn(DateTimeInterface | CarbonInterface $date): self
    {
        $this->builder->anchorBillingCycleOn($date);

        return $this;
    }

    /**
     * Apply a coupon.
     *
     * Uses the vouchers package integration in cashier-chip.
     */
    public function withCoupon(?string $coupon): self
    {
        if ($coupon && method_exists($this->builder, 'withCoupon')) {
            $this->builder->withCoupon($coupon);
        }

        return $this;
    }

    /**
     * Apply a promotion code.
     *
     * Uses the vouchers package integration in cashier-chip.
     */
    public function withPromotionCode(?string $promotionCode): self
    {
        if ($promotionCode && method_exists($this->builder, 'withPromotionCode')) {
            $this->builder->withPromotionCode($promotionCode);
        }

        return $this;
    }

    /**
     * Set metadata on the subscription.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->builder->withMetadata($metadata);

        return $this;
    }

    /**
     * Allow incomplete payments / payment failures.
     */
    public function allowPaymentFailures(): self
    {
        if (method_exists($this->builder, 'allowPaymentFailures')) {
            $this->builder->allowPaymentFailures();
        }

        return $this;
    }

    /**
     * Add a new subscription without immediate payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function add(array $options = []): SubscriptionContract
    {
        $subscription = $this->builder->add($options);

        return new ChipSubscription($subscription);
    }

    /**
     * Create the subscription with payment.
     *
     * @param  array<string, mixed>  $options
     */
    public function create(?string $paymentMethod = null, array $options = []): SubscriptionContract
    {
        $subscription = $this->builder->create($paymentMethod, $options);

        return new ChipSubscription($subscription);
    }

    /**
     * Create a checkout session for the subscription.
     *
     * @param  array<string, mixed>  $sessionOptions
     */
    public function checkout(array $sessionOptions = []): CheckoutContract
    {
        $checkout = $this->builder->checkout($sessionOptions);

        return new ChipCheckout($checkout->asChipPurchase());
    }

    /**
     * Get the subscription type.
     */
    public function getType(): string
    {
        return $this->builder->getType();
    }

    /**
     * Get the items/prices on the builder.
     *
     * @return array<string, mixed>
     */
    public function getItems(): array
    {
        return $this->builder->getItems();
    }

    /**
     * Get the trial end date.
     */
    public function getTrialEnd(): ?CarbonInterface
    {
        return $this->builder->getTrialEnd();
    }

    /**
     * Get the metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->builder->getMetadata();
    }

    /**
     * Get the underlying builder.
     */
    public function asGatewayBuilder(): SubscriptionBuilder
    {
        return $this->builder;
    }
}
