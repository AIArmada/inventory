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
        $price = max(0, $price);
        $quantity = max(1, $quantity);

        $this->products[] = [
            'name' => $name,
            'price' => $price,
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
        $normalized = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $name = $product['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'price' => max(0, (int) ($product['price'] ?? 0)),
                'quantity' => max(1, (int) ($product['quantity'] ?? 1)),
                'discount' => max(0, (int) ($product['discount'] ?? 0)),
            ];
        }

        $this->products = $normalized;

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

                if ($discount > 0 && ! empty($this->products)) {
                    $firstQuantity = (int) ($this->products[0]['quantity'] ?? 1);
                    $firstPrice = (int) ($this->products[0]['price'] ?? 0);
                    $firstTotal = max(0, $firstPrice * max(1, $firstQuantity));

                    $applied = min($discount, $firstTotal);
                    $this->products[0]['discount'] = (int) ($this->products[0]['discount'] ?? 0) + $applied;
                }
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
