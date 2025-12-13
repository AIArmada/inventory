<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Concerns\AllowsCoupons;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Conditionable;

/**
 * Fluent builder for creating CHIP checkout sessions.
 */
class CheckoutBuilder
{
    use AllowsCoupons;
    use Conditionable;

    /**
     * The model that is checking out.
     */
    protected ?Model $owner;

    /**
     * Whether to request a recurring token.
     */
    protected bool $recurring = false;

    /**
     * The success URL.
     */
    protected ?string $successUrl = null;

    /**
     * The cancel URL.
     */
    protected ?string $cancelUrl = null;

    /**
     * The webhook URL.
     */
    protected ?string $webhookUrl = null;

    /**
     * The metadata for the checkout session.
     */
    protected array $metadata = [];

    /**
     * The products for the checkout session.
     */
    protected array $products = [];

    /**
     * The currency for the checkout.
     */
    protected ?string $currency = null;

    /**
     * Create a new checkout builder instance.
     *
     * @param  Model|null  $owner
     * @return void
     */
    public function __construct($owner = null)
    {
        $this->owner = $owner;
    }

    /**
     * Request a recurring token for future payments.
     *
     * @return $this
     */
    public function recurring(bool $recurring = true)
    {
        $this->recurring = $recurring;

        return $this;
    }

    /**
     * Set the success URL.
     *
     * @return $this
     */
    public function successUrl(string $url)
    {
        $this->successUrl = $url;

        return $this;
    }

    /**
     * Set the cancel URL.
     *
     * @return $this
     */
    public function cancelUrl(string $url)
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Set the webhook URL.
     *
     * @return $this
     */
    public function webhookUrl(string $url)
    {
        $this->webhookUrl = $url;

        return $this;
    }

    /**
     * Set the metadata for the checkout session.
     *
     * @return $this
     */
    public function withMetadata(array $metadata)
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Add a product to the checkout.
     *
     * @param  int  $price  Price in cents
     * @return $this
     */
    public function addProduct(string $name, int $price, int $quantity = 1)
    {
        $this->products[] = [
            'name' => $name,
            'price' => $price / 100, // Convert to decimal for CHIP
            'quantity' => $quantity,
        ];

        return $this;
    }

    /**
     * Set the products for checkout.
     *
     * @return $this
     */
    public function products(array $products)
    {
        $this->products = $products;

        return $this;
    }

    /**
     * Set the currency.
     *
     * @return $this
     */
    public function currency(string $currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Create the checkout session.
     *
     * @param  int  $amount  Amount in cents
     */
    public function create(int $amount, array $options = []): Checkout
    {
        // Apply coupon discount if present
        $couponId = $this->couponId ?? $this->promotionCodeId;

        if ($couponId) {
            $this->validateCouponForCheckout($couponId);

            $coupon = $this->retrieveCoupon($couponId);

            if ($coupon) {
                $discount = $coupon->calculateDiscount($amount);
                $amount = max(0, $amount - $discount);

                // Store coupon in metadata for tracking
                $this->metadata['coupon_id'] = $couponId;
                $this->metadata['coupon_discount'] = $discount;
            }
        }

        $options = array_merge([
            'recurring' => $this->recurring,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'webhook_url' => $this->webhookUrl,
            'metadata' => $this->metadata,
            'products' => $this->products ?: null,
            'currency' => $this->currency,
            'allow_promotion_codes' => $this->allowPromotionCodes,
        ], $options);

        // Remove null values
        $options = array_filter($options, fn ($value) => ! is_null($value));

        return Checkout::create($this->owner, $amount, $options);
    }

    /**
     * Create a checkout for a single charge.
     *
     * @param  int  $amount  Amount in cents
     */
    public function charge(int $amount, string $description = 'Payment', array $options = []): Checkout
    {
        return $this->create($amount, array_merge([
            'reference' => $description,
        ], $options));
    }
}
