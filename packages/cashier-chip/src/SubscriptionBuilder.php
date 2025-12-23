<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Concerns\AllowsCoupons;
use AIArmada\CashierChip\Concerns\HandlesPaymentFailures;
use AIArmada\CashierChip\Concerns\InteractsWithPaymentBehavior;
use AIArmada\CashierChip\Concerns\Prorates;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;

class SubscriptionBuilder
{
    use AllowsCoupons;
    use Conditionable;
    use HandlesPaymentFailures;
    use InteractsWithPaymentBehavior;
    use Prorates;

    /**
     * The model that is subscribing.
     */
    /**
     * @phpstan-var Model&BillableContract
     */
    protected Model $owner;

    /**
     * The type of the subscription.
     */
    protected string $type;

    /**
     * The prices the customer is being subscribed to.
     */
    protected array $items = [];

    /**
     * The date and time the trial will expire.
     */
    protected ?CarbonInterface $trialExpires = null;

    /**
     * Indicates that the trial should end immediately.
     */
    protected bool $skipTrial = false;

    /**
     * The billing interval (day, week, month, year).
     */
    protected string $billingInterval = 'month';

    /**
     * The billing interval count.
     */
    protected int $billingIntervalCount = 1;

    /**
     * The date on which the billing cycle should be anchored.
     */
    protected ?CarbonInterface $billingCycleAnchor = null;

    /**
     * The metadata to apply to the subscription.
     */
    protected array $metadata = [];

    /**
     * Create a new subscription builder instance.
     *
     * @param  string|string[]|array[]  $prices
     */
    /**
     * @phpstan-param Model&BillableContract $owner
     */
    public function __construct(Model $owner, string $type, string | array $prices = [])
    {
        $this->type = $type;
        $this->owner = $owner;

        foreach ((array) $prices as $price) {
            $this->price($price);
        }
    }

    /**
     * Set a price on the subscription builder.
     *
     * @return $this
     */
    public function price(string | array $price, ?int $quantity = 1)
    {
        $options = is_array($price) ? $price : ['price' => $price];

        $resolvedQuantity = $options['quantity'] ?? $quantity;

        if ($resolvedQuantity !== null) {
            $options['quantity'] = max(1, (int) $resolvedQuantity);
        }

        if (isset($options['unit_amount'])) {
            $unitAmount = (int) $options['unit_amount'];

            if ($unitAmount < 0) {
                throw new InvalidArgumentException('Subscription item unit amount must be a non-negative integer.');
            }

            $options['unit_amount'] = $unitAmount;
        }

        if (! isset($options['price']) || ! is_string($options['price']) || $options['price'] === '') {
            throw new InvalidArgumentException('A non-empty price identifier is required.');
        }

        if (isset($options['price'])) {
            $this->items[$options['price']] = $options;
        } else {
            $this->items[] = $options;
        }

        return $this;
    }

    /**
     * Specify the quantity of a subscription item.
     *
     * @return $this
     */
    public function quantity(?int $quantity, ?string $price = null)
    {
        if (is_null($price)) {
            if (empty($this->items)) {
                throw new InvalidArgumentException('No price specified for quantity update.');
            }

            if (count($this->items) > 1) {
                throw new InvalidArgumentException('Price is required when creating subscriptions with multiple prices.');
            }

            $price = Arr::first($this->items)['price'];
        }

        return $this->price($price, $quantity);
    }

    /**
     * Specify the number of days of the trial.
     *
     * @return $this
     */
    public function trialDays(int $trialDays)
    {
        $this->trialExpires = Carbon::now()->addDays($trialDays);

        return $this;
    }

    /**
     * Specify the ending date of the trial.
     *
     * @return $this
     */
    public function trialUntil(CarbonInterface $trialUntil)
    {
        $this->trialExpires = $trialUntil;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->skipTrial = true;

        return $this;
    }

    /**
     * Set the billing interval.
     *
     * @param  string  $interval  (day, week, month, year)
     * @return $this
     */
    public function billingInterval(string $interval, int $count = 1)
    {
        $this->billingInterval = $interval;
        $this->billingIntervalCount = $count;

        return $this;
    }

    /**
     * Set monthly billing.
     *
     * @return $this
     */
    public function monthly(int $count = 1)
    {
        return $this->billingInterval('month', $count);
    }

    /**
     * Set yearly billing.
     *
     * @return $this
     */
    public function yearly(int $count = 1)
    {
        return $this->billingInterval('year', $count);
    }

    /**
     * Set weekly billing.
     *
     * @return $this
     */
    public function weekly(int $count = 1)
    {
        return $this->billingInterval('week', $count);
    }

    /**
     * Set daily billing.
     *
     * @return $this
     */
    public function daily(int $count = 1)
    {
        return $this->billingInterval('day', $count);
    }

    /**
     * Change the billing cycle anchor on a subscription creation.
     *
     * @return $this
     */
    public function anchorBillingCycleOn(DateTimeInterface | CarbonInterface $date)
    {
        $this->billingCycleAnchor = Carbon::instance($date);

        return $this;
    }

    /**
     * The metadata to apply to a new subscription.
     *
     * @return $this
     */
    public function withMetadata(array $metadata)
    {
        $this->metadata = (array) $metadata;

        return $this;
    }

    /**
     * Add a new subscription without immediate payment.
     */
    public function add(array $options = []): Subscription
    {
        return $this->create(null, $options);
    }

    /**
     * Create a new subscription.
     *
     * Since CHIP doesn't have native subscriptions, we:
     * 1. Ensure the customer exists in CHIP
     * 2. Create local subscription record
     * 3. Optionally charge immediately or wait for first billing date
     *
     * @param  string|null  $recurringToken  The CHIP recurring token for payments
     *
     * @throws Exception
     */
    public function create(?string $recurringToken = null, array $options = []): Subscription
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        // Ensure the customer exists (only if they don't have a chip_id already)
        if (method_exists($this->owner, 'createOrGetChipCustomer') && ! $this->owner->hasChipId()) {
            $this->owner->createOrGetChipCustomer();
        }

        // Validate coupon if provided
        $couponId = $this->couponId ?? $this->promotionCodeId;
        $couponDiscount = 0;
        $couponDuration = null;

        if ($couponId) {
            $this->validateCouponForSubscriptionApplication($couponId);

            $coupon = $this->retrieveCoupon($couponId);

            if ($coupon) {
                $totalAmount = $this->calculateTotalAmount();
                $couponDiscount = $coupon->calculateDiscount($totalAmount);
                $couponDuration = $coupon->duration();
            }
        }

        // Calculate the next billing date
        $nextBillingAt = $this->calculateNextBillingDate();

        // Calculate trial end
        $trialEndsAt = ! $this->skipTrial ? $this->trialExpires : null;

        // If there's a trial, set next billing to after trial
        if ($trialEndsAt) {
            $nextBillingAt = $trialEndsAt->copy()->add($this->billingInterval, $this->billingIntervalCount);
        }

        // Determine initial status
        $status = $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE;

        // Get the first item to set on subscription
        $firstItem = Arr::first($this->items);
        $isSinglePrice = count($this->items) === 1;

        return DB::transaction(function () use ($status, $trialEndsAt, $nextBillingAt, $recurringToken, $firstItem, $isSinglePrice, $couponId, $couponDiscount, $couponDuration): Subscription {
            $ownerAttributes = $this->resolveTenantOwnerAttributes();

            /** @var Subscription $subscription */
            $subscription = $this->owner->subscriptions()->create([
                ...$ownerAttributes,
                'type' => $this->type,
                'chip_id' => Str::uuid()->toString(),
                'chip_status' => $status,
                'chip_price' => $isSinglePrice ? ($firstItem['price'] ?? null) : null,
                'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? 1) : null,
                'trial_ends_at' => $trialEndsAt,
                'next_billing_at' => $nextBillingAt,
                'billing_interval' => $this->billingInterval,
                'billing_interval_count' => $this->billingIntervalCount,
                'recurring_token' => $recurringToken ?? $this->owner->defaultPaymentMethod()?->id(),
                'ends_at' => null,
                'coupon_id' => $couponId,
                'coupon_discount' => $couponDiscount,
                'coupon_duration' => $couponDuration,
                'coupon_applied_at' => $couponId ? Carbon::now() : null,
            ]);

            // Create subscription items
            foreach ($this->items as $item) {
                $subscription->items()->create([
                    ...$ownerAttributes,
                    'chip_id' => Str::uuid()->toString(),
                    'chip_product' => $item['product'] ?? null,
                    'chip_price' => $item['price'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_amount' => $item['unit_amount'] ?? null,
                ]);
            }

            // Record coupon usage
            if ($couponId && $couponDiscount > 0) {
                $this->recordCouponUsage($couponId, $couponDiscount, $this->owner);
            }

            return $subscription;
        });
    }

    /**
     * @return array{owner_type?: string, owner_id?: string}
     */
    private function resolveTenantOwnerAttributes(): array
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return [];
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            throw new AuthorizationException('Owner context is required to create subscriptions when owner scoping is enabled.');
        }

        return [
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ];
    }

    /**
     * Create a new subscription and charge immediately.
     *
     * @param  int  $amount  Amount in cents
     */
    public function createAndCharge(?string $recurringToken = null, int $amount = 0, array $options = []): Subscription
    {
        $subscription = $this->create($recurringToken, $options);

        // If not on trial, charge immediately
        if (! $subscription->onTrial() && $amount > 0) {
            $subscription->charge($amount);
        }

        return $subscription;
    }

    /**
     * Begin a new Checkout Session for the subscription.
     *
     * @return Checkout
     */
    public function checkout(array $sessionOptions = [])
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        // Calculate the total amount from items
        $amount = $this->calculateTotalAmount();

        // Apply coupon discount if present
        $couponId = $this->couponId ?? $this->promotionCodeId;

        if ($couponId) {
            $this->validateCouponForCheckout($couponId);

            $coupon = $this->retrieveCoupon($couponId);

            if ($coupon) {
                $discount = $coupon->calculateDiscount($amount);
                $amount = max(0, $amount - $discount);
            }
        }

        // Build the checkout session
        $metadata = array_merge($this->metadata, [
            'subscription_type' => $this->type,
            'billing_interval' => $this->billingInterval,
            'billing_interval_count' => $this->billingIntervalCount,
            'items' => json_encode($this->items),
        ]);

        if ($this->trialExpires) {
            $metadata['trial_ends_at'] = $this->trialExpires->toIso8601String();
        }

        if ($couponId) {
            $metadata['coupon_id'] = $couponId;
        }

        $checkout = Checkout::customer($this->owner)
            ->recurring()
            ->withMetadata($metadata);

        // Pass coupon settings to checkout
        if ($this->couponId) {
            $checkout->withCoupon($this->couponId);
        }

        if ($this->promotionCodeId) {
            $checkout->withPromotionCode($this->promotionCodeId);
        }

        if ($this->allowPromotionCodes) {
            $checkout->allowPromotionCodes();
        }

        return $checkout->create(
            $amount,
            array_merge([
                'reference' => "Subscription: {$this->type}",
            ], $sessionOptions)
        );
    }

    /**
     * Get the items set on the subscription builder.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get the subscription type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the trial end date.
     */
    public function getTrialEnd(): ?CarbonInterface
    {
        return $this->trialExpires;
    }

    /**
     * Check if the trial will be skipped.
     */
    public function getSkipTrial(): bool
    {
        return $this->skipTrial;
    }

    /**
     * Get the billing interval.
     */
    public function getBillingInterval(): string
    {
        return $this->billingInterval;
    }

    /**
     * Get the billing interval count.
     */
    public function getBillingIntervalCount(): int
    {
        return $this->billingIntervalCount;
    }

    /**
     * Get the billing cycle anchor.
     */
    public function getBillingCycleAnchor(): ?CarbonInterface
    {
        return $this->billingCycleAnchor;
    }

    /**
     * Get the metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Calculate the next billing date.
     */
    protected function calculateNextBillingDate(): CarbonInterface
    {
        if ($this->billingCycleAnchor) {
            return $this->billingCycleAnchor->copy();
        }

        return Carbon::now()->add($this->billingInterval, $this->billingIntervalCount);
    }

    /**
     * Calculate the total amount from all items.
     */
    protected function calculateTotalAmount(): int
    {
        return collect($this->items)->sum(function ($item) {
            return ($item['unit_amount'] ?? 0) * ($item['quantity'] ?? 1);
        });
    }
}
