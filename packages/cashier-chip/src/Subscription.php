<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Concerns\HandlesPaymentFailures;
use AIArmada\CashierChip\Concerns\InteractsWithPaymentBehavior;
use AIArmada\CashierChip\Concerns\Prorates;
use AIArmada\CashierChip\Database\Factories\SubscriptionFactory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * CHIP Subscription Model
 *
 * Unlike Stripe, CHIP doesn't have native subscription management.
 * This model manages subscriptions locally with CHIP recurring tokens for payment.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $chip_id
 * @property string $chip_status
 * @property string|null $chip_price
 * @property int|null $quantity
 * @property string|null $recurring_token
 * @property string $billing_interval
 * @property int $billing_interval_count
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $next_billing_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property string|null $coupon_id
 * @property int|null $coupon_discount
 * @property string|null $coupon_duration
 * @property \Illuminate\Support\Carbon|null $coupon_applied_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SubscriptionItem> $items
 */
class Subscription extends Model
{
    use HandlesPaymentFailures;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasUuids;
    use InteractsWithPaymentBehavior;
    use Prorates;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAUSED = 'paused';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array<int, string>
     */
    protected $with = ['items'];

    public function getTable(): string
    {
        $tables = config('cashier-chip.database.tables', []);
        $prefix = config('cashier-chip.database.table_prefix', 'cashier_chip_');

        return $tables['subscriptions'] ?? $prefix.'subscriptions';
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     */
    public function owner(): BelongsTo
    {
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionItemModel);
    }

    /**
     * Determine if the subscription has multiple prices.
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->chip_price);
    }

    /**
     * Determine if the subscription has a single price.
     */
    public function hasSinglePrice(): bool
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Determine if the subscription has a specific product.
     */
    public function hasProduct(string $product): bool
    {
        return $this->items->contains(function (SubscriptionItem $item) use ($product) {
            return $item->chip_product === $product;
        });
    }

    /**
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->chip_price === $price;
            });
        }

        return $this->chip_price === $price;
    }

    /**
     * Get the subscription item for the given price.
     *
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return $this->items()->where('chip_price', $price)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->chip_status === self::STATUS_INCOMPLETE;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeIncomplete(Builder $query): void
    {
        $query->where('chip_status', self::STATUS_INCOMPLETE);
    }

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->chip_status === self::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('chip_status', self::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return ! $this->ended() &&
            (! Cashier::$deactivateIncomplete || $this->chip_status !== self::STATUS_INCOMPLETE) &&
            $this->chip_status !== self::STATUS_INCOMPLETE_EXPIRED &&
            (! Cashier::$deactivatePastDue || $this->chip_status !== self::STATUS_PAST_DUE) &&
            $this->chip_status !== self::STATUS_UNPAID;
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(function ($query): void {
            $query->whereNull('ends_at')
                ->orWhere(function ($query): void {
                    $query->onGracePeriod();
                });
        })->where('chip_status', '!=', self::STATUS_INCOMPLETE_EXPIRED)
            ->where('chip_status', '!=', self::STATUS_UNPAID);

        if (Cashier::$deactivatePastDue) {
            $query->where('chip_status', '!=', self::STATUS_PAST_DUE);
        }

        if (Cashier::$deactivateIncomplete) {
            $query->where('chip_status', '!=', self::STATUS_INCOMPLETE);
        }
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeRecurring(Builder $query): void
    {
        $query->notOnTrial()->notCanceled();
    }

    /**
     * Determine if the subscription is no longer active.
     */
    public function canceled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not canceled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeNotCanceled(Builder $query): void
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeEnded(Builder $query): void
    {
        $query->canceled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter query by expired trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeExpiredTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeNotOnTrial(Builder $query): void
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeOnGracePeriod(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopeNotOnGracePeriod(Builder $query): void
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @return $this
     */
    public function incrementQuantity(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->incrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return $this->updateQuantity($this->quantity + $count, $price);
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @return $this
     */
    public function decrementQuantity(int $count = 1, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->decrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return $this->updateQuantity(max(1, $this->quantity - $count), $price);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @return $this
     */
    public function updateQuantity(int $quantity, ?string $price = null)
    {
        $this->guardAgainstIncomplete();

        if ($price) {
            $this->findItemOrFail($price)->updateQuantity($quantity);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePrices();

        return DB::transaction(function () use ($quantity) {
            $this->fill([
                'quantity' => $quantity,
            ])->save();

            $singleSubscriptionItem = $this->items()->firstOrFail();

            $singleSubscriptionItem->fill([
                'quantity' => $quantity,
            ])->save();

            return $this;
        });
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Force the subscription's trial to end immediately.
     *
     * @return $this
     */
    public function endTrial()
    {
        if (is_null($this->trial_ends_at)) {
            return $this;
        }

        $this->trial_ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Extend an existing subscription's trial period.
     *
     * @return $this
     */
    public function extendTrial(CarbonInterface $date)
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $this->trial_ends_at = $date;
        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to new prices.
     *
     * @return $this
     */
    public function swap(string|array $prices, array $options = [])
    {
        if (empty($prices = (array) $prices)) {
            throw new InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $this->guardAgainstIncomplete();

        return DB::transaction(function () use ($prices, $options) {
            $isSinglePrice = count($prices) === 1;
            $firstPrice = is_string(array_values($prices)[0])
                ? array_values($prices)[0]
                : array_keys($prices)[0];

            // Delete existing items and create new ones
            $this->items()->delete();

            foreach ($prices as $priceKey => $priceValue) {
                $price = is_string($priceValue) ? $priceValue : $priceKey;
                $quantity = is_array($priceValue) ? ($priceValue['quantity'] ?? 1) : 1;

                $this->items()->create([
                    'chip_id' => 'si_'.uniqid().'_'.time(),
                    'chip_product' => $options['product'] ?? null,
                    'chip_price' => $price,
                    'quantity' => $quantity,
                ]);
            }

            $this->fill([
                'chip_price' => $isSinglePrice ? $firstPrice : null,
                'quantity' => $isSinglePrice ? ($this->items()->first()->quantity ?? null) : null,
                'ends_at' => null,
            ])->save();

            $this->unsetRelation('items');

            return $this;
        });
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll use the next billing date as the end of
        // the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = $this->next_billing_at ?? Carbon::now();
        }

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     *
     * @return $this
     */
    public function cancelAt(DateTimeInterface|int $endsAt)
    {
        if ($endsAt instanceof DateTimeInterface) {
            $endsAt = Carbon::instance($endsAt);
        } else {
            $endsAt = Carbon::createFromTimestamp($endsAt);
        }

        $this->ends_at = $endsAt;
        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $this->markAsCanceled();

        return $this;
    }

    /**
     * Mark the subscription as canceled.
     *
     *
     * @internal
     */
    public function markAsCanceled(): void
    {
        $this->fill([
            'chip_status' => self::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Resume the canceled subscription.
     *
     * @return $this
     *
     * @throws LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "canceled". Then we shall save this record in the database.
        $this->fill([
            'chip_status' => self::STATUS_ACTIVE,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Get the current period start date for the subscription.
     */
    public function currentPeriodStart(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        // Calculate the period start based on billing interval
        $interval = $this->billing_interval ?? 'month';
        $start = $this->next_billing_at->copy()->sub($interval, 1);

        return $timezone ? $start->setTimezone($timezone) : $start;
    }

    /**
     * Get the current period end date for the subscription.
     */
    public function currentPeriodEnd(DateTimeZone|string|int|null $timezone = null): ?CarbonInterface
    {
        if (! $this->next_billing_at) {
            return null;
        }

        return $timezone ? $this->next_billing_at->copy()->setTimezone($timezone) : $this->next_billing_at->copy();
    }

    /**
     * Charge the subscription using the default payment method (recurring token).
     *
     * @return Payment
     */
    public function charge(?int $amount = null)
    {
        $amount = $amount ?? $this->calculateSubscriptionAmount();

        return $this->owner->chargeWithRecurringToken(
            $amount,
            $this->owner->defaultPaymentMethod(),
            [
                'reference' => "Subscription {$this->type} - Period {$this->next_billing_at?->format('Y-m-d')}",
            ]
        );
    }

    /**
     * Get the recurring token (payment method) for this subscription.
     */
    public function recurringToken(): ?string
    {
        return $this->recurring_token ?? $this->owner->defaultPaymentMethod();
    }

    /**
     * Set the recurring token for this subscription.
     *
     * @return $this
     */
    public function setRecurringToken(string $token)
    {
        $this->recurring_token = $token;
        $this->save();

        return $this;
    }

    /**
     * Determine if the subscription has an incomplete payment.
     */
    public function hasIncompletePayment(): bool
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Determine if the subscription has a discount applied.
     */
    public function hasDiscount(): bool
    {
        return ! is_null($this->coupon_id);
    }

    /**
     * The discount that applies to the subscription, if applicable.
     */
    public function discount(): ?Discount
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        return new Discount([
            'coupon' => $this->coupon_id,
            'amount' => $this->coupon_discount,
            'start' => $this->coupon_applied_at,
            'end' => $this->calculateDiscountEnd(),
            'currency' => $this->owner->preferredCurrency(),
        ]);
    }

    /**
     * Get all discounts that apply to the subscription.
     *
     * @return \Illuminate\Support\Collection<int, Discount>
     */
    public function discounts(): \Illuminate\Support\Collection
    {
        $discount = $this->discount();

        if (! $discount) {
            return collect();
        }

        return collect([$discount]);
    }

    /**
     * Apply a coupon to the subscription.
     *
     * @throws Exceptions\InvalidCoupon
     */
    public function applyCoupon(string $couponId): void
    {
        $this->validateCouponForApplication($couponId);

        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw Exceptions\InvalidCoupon::notFound($couponId);
        }

        $totalAmount = $this->calculateSubscriptionAmount();
        $discount = $coupon->calculateDiscount($totalAmount);

        $this->fill([
            'coupon_id' => $couponId,
            'coupon_discount' => $discount,
            'coupon_duration' => $coupon->duration(),
            'coupon_applied_at' => Carbon::now(),
        ])->save();

        // Record coupon usage
        $this->recordCouponUsage($couponId, $discount);
    }

    /**
     * Apply a promotion code to the subscription.
     */
    public function applyPromotionCode(string $promotionCodeId): void
    {
        // For CHIP/Vouchers, promotion codes are the same as coupon codes
        $this->applyCoupon($promotionCodeId);
    }

    /**
     * Remove the discount from the subscription.
     */
    public function removeDiscount(): void
    {
        $this->fill([
            'coupon_id' => null,
            'coupon_discount' => null,
            'coupon_duration' => null,
            'coupon_applied_at' => null,
        ])->save();
    }

    /**
     * Get the latest payment for a Subscription.
     */
    public function latestPayment(): ?Payment
    {
        // For CHIP, we would need to track payments separately
        // This is a placeholder implementation
        return null;
    }

    /**
     * Sync the CHIP status of the subscription.
     *
     * Since CHIP doesn't have native subscriptions, this method
     * recalculates the status based on local state.
     */
    public function syncChipStatus(): void
    {
        $status = $this->calculateCurrentStatus();

        $this->chip_status = $status;
        $this->save();
    }

    /**
     * Add a new price to the subscription.
     *
     * @return $this
     *
     * @throws Exceptions\SubscriptionUpdateFailure
     */
    public function addPrice(string $price, ?int $quantity = 1, array $options = []): static
    {
        $this->guardAgainstIncomplete();

        if ($this->items->contains('chip_price', $price)) {
            throw Exceptions\SubscriptionUpdateFailure::duplicatePrice($this, $price);
        }

        $this->items()->create([
            'chip_id' => 'si_'.uniqid().'_'.time(),
            'chip_product' => $options['product'] ?? null,
            'chip_price' => $price,
            'quantity' => $quantity,
            'unit_amount' => $options['unit_amount'] ?? null,
        ]);

        $this->unsetRelation('items');

        if ($this->hasSinglePrice()) {
            $this->fill([
                'chip_price' => null,
                'quantity' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Remove a price from the subscription.
     *
     * @return $this
     *
     * @throws Exceptions\SubscriptionUpdateFailure
     */
    public function removePrice(string $price): static
    {
        if ($this->hasSinglePrice()) {
            throw Exceptions\SubscriptionUpdateFailure::cannotDeleteLastPrice($this);
        }

        $this->items()->where('chip_price', $price)->delete();

        $this->unsetRelation('items');

        if ($this->items()->count() < 2) {
            $item = $this->items()->first();

            $this->fill([
                'chip_price' => $item->chip_price,
                'quantity' => $item->quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Get the upcoming invoice for the subscription.
     */
    public function upcomingInvoice(): ?Invoice
    {
        if ($this->canceled()) {
            return null;
        }

        // For CHIP, upcoming invoices would need to be calculated locally
        // This is a placeholder - implement based on your business logic
        return null;
    }

    /**
     * Get the latest invoice for the subscription.
     */
    public function latestInvoice(): ?Invoice
    {
        // For CHIP, invoices would need to be tracked separately
        // This is a placeholder - implement based on your invoice storage
        return null;
    }

    /**
     * Get a collection of the subscription's invoices.
     *
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function invoices(): \Illuminate\Support\Collection
    {
        // For CHIP, invoices would need to be tracked separately
        return collect();
    }

    /**
     * Determine if the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->chip_status === self::STATUS_PAUSED;
    }

    /**
     * Filter query by paused.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function scopePaused(Builder $query): void
    {
        $query->where('chip_status', self::STATUS_PAUSED);
    }

    /**
     * Pause the subscription.
     *
     * @return $this
     */
    public function pause(): static
    {
        $this->fill([
            'chip_status' => self::STATUS_PAUSED,
        ])->save();

        return $this;
    }

    /**
     * Unpause the subscription.
     *
     * @return $this
     */
    public function unpause(): static
    {
        $this->fill([
            'chip_status' => self::STATUS_ACTIVE,
        ])->save();

        return $this;
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @throws Exceptions\SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete(): void
    {
        if ($this->incomplete()) {
            throw Exceptions\SubscriptionUpdateFailure::incompleteSubscription($this);
        }
    }

    /**
     * Make sure a price argument is provided when the subscription is a subscription with multiple prices.
     *
     * @throws InvalidArgumentException
     */
    public function guardAgainstMultiplePrices(): void
    {
        if ($this->hasMultiplePrices()) {
            throw new InvalidArgumentException(
                'This method requires a price argument since the subscription has multiple prices.'
            );
        }
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Subscription $subscription): void {
            $subscription->items()->delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'quantity' => 'integer',
            'trial_ends_at' => 'datetime',
            'next_billing_at' => 'datetime',
            'coupon_discount' => 'integer',
            'coupon_applied_at' => 'datetime',
        ];
    }

    /**
     * Validate that a coupon can be applied to the subscription.
     *
     * @throws Exceptions\InvalidCoupon
     */
    protected function validateCouponForApplication(string $couponId): void
    {
        $coupon = $this->retrieveCoupon($couponId);

        if (! $coupon) {
            throw Exceptions\InvalidCoupon::notFound($couponId);
        }

        if (! $coupon->isValid()) {
            throw Exceptions\InvalidCoupon::inactive($couponId);
        }

        if ($coupon->isForeverAmountOff()) {
            throw Exceptions\InvalidCoupon::cannotApplyForeverAmountOffToSubscription($couponId);
        }
    }

    /**
     * Retrieve a coupon by its ID (voucher code).
     */
    protected function retrieveCoupon(string $couponId): ?Coupon
    {
        if (! class_exists(\AIArmada\Vouchers\Services\VoucherService::class)) {
            return null;
        }

        /** @var \AIArmada\Vouchers\Services\VoucherService $service */
        $service = app(\AIArmada\Vouchers\Services\VoucherService::class);

        $voucherData = $service->find($couponId);

        if (! $voucherData) {
            return null;
        }

        return new Coupon($voucherData);
    }

    /**
     * Record coupon usage.
     */
    protected function recordCouponUsage(string $couponId, int $discountAmount): void
    {
        if (! class_exists(\AIArmada\Vouchers\Services\VoucherService::class)) {
            return;
        }

        /** @var \AIArmada\Vouchers\Services\VoucherService $service */
        $service = app(\AIArmada\Vouchers\Services\VoucherService::class);

        $currency = $this->owner->preferredCurrency();

        $service->recordUsage(
            code: $couponId,
            discountAmount: \Akaunting\Money\Money::$currency($discountAmount),
            channel: 'subscription',
            metadata: ['subscription_id' => $this->id],
            redeemedBy: $this->owner,
        );
    }

    /**
     * Calculate when the discount ends based on duration.
     */
    protected function calculateDiscountEnd(): ?CarbonInterface
    {
        if (! $this->coupon_applied_at || ! $this->coupon_duration) {
            return null;
        }

        return match ($this->coupon_duration) {
            'once' => $this->next_billing_at,
            'forever' => null,
            'repeating' => $this->coupon_applied_at->copy()->addMonths(
                $this->retrieveCoupon($this->coupon_id)?->durationInMonths() ?? 1
            ),
            default => null,
        };
    }

    /**
     * Calculate the total subscription amount based on items.
     */
    protected function calculateSubscriptionAmount(): int
    {
        return $this->items->sum(function ($item) {
            return ($item->unit_amount ?? 0) * ($item->quantity ?? 1);
        });
    }

    /**
     * Calculate the current status based on subscription state.
     */
    protected function calculateCurrentStatus(): string
    {
        if ($this->ended()) {
            return self::STATUS_CANCELED;
        }

        if ($this->onTrial()) {
            return self::STATUS_TRIALING;
        }

        if ($this->onGracePeriod()) {
            return self::STATUS_CANCELED;
        }

        return self::STATUS_ACTIVE;
    }
}
