<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Stripe;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Contracts\SubscriptionItemContract;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Cashier\Subscription;

/**
 * Wrapper for Stripe subscription.
 *
 * This class wraps a Laravel Cashier Subscription model and adapts it
 * to the unified SubscriptionContract interface.
 */
class StripeSubscription implements SubscriptionContract
{
    protected Subscription $subscription;

    /**
     * Create a new Stripe subscription wrapper.
     */
    public function __construct(mixed $subscription)
    {
        if (! $subscription instanceof Subscription) {
            throw new InvalidArgumentException('StripeSubscription expects an instance of ' . Subscription::class);
        }

        $this->subscription = $subscription;
    }

    /**
     * Get the subscription ID.
     */
    public function id(): string
    {
        return (string) $this->subscription->id;
    }

    /**
     * Get the gateway subscription ID.
     */
    public function gatewayId(): string
    {
        return $this->subscription->stripe_id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'stripe';
    }

    /**
     * Get the subscription type.
     */
    public function type(): string
    {
        return $this->subscription->type;
    }

    /**
     * Determine if the subscription is valid.
     */
    public function valid(): bool
    {
        return $this->subscription->valid();
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return $this->subscription->active();
    }

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->subscription->onTrial();
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->subscription->hasExpiredTrial();
    }

    /**
     * Determine if the subscription is canceled.
     */
    public function canceled(): bool
    {
        return $this->subscription->canceled();
    }

    /**
     * Determine if the subscription has ended.
     */
    public function ended(): bool
    {
        return $this->subscription->ended();
    }

    /**
     * Determine if the subscription is on grace period.
     */
    public function onGracePeriod(): bool
    {
        return $this->subscription->onGracePeriod();
    }

    /**
     * Determine if the subscription is recurring.
     */
    public function recurring(): bool
    {
        return $this->subscription->recurring();
    }

    /**
     * Determine if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->subscription->pastDue();
    }

    /**
     * Determine if the subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->subscription->incomplete();
    }

    /**
     * Determine if the subscription has an incomplete payment.
     */
    public function hasIncompletePayment(): bool
    {
        return $this->subscription->hasIncompletePayment();
    }

    /**
     * Get the trial end date.
     */
    public function trialEndsAt(): ?CarbonInterface
    {
        return $this->subscription->trial_ends_at;
    }

    /**
     * Get the subscription end date.
     */
    public function endsAt(): ?CarbonInterface
    {
        return $this->subscription->ends_at;
    }

    /**
     * Get the current period start.
     */
    public function currentPeriodStart(): ?CarbonInterface
    {
        return $this->subscription->currentPeriodStart();
    }

    /**
     * Get the current period end.
     */
    public function currentPeriodEnd(): ?CarbonInterface
    {
        return $this->subscription->currentPeriodEnd();
    }

    /**
     * Get the subscription items.
     *
     * @return Collection<int, SubscriptionItemContract>
     */
    public function items(): Collection
    {
        return $this->subscription->items->map(fn ($item) => new StripeSubscriptionItem($item));
    }

    /**
     * Get the owner of the subscription.
     */
    public function owner(): BillableContract
    {
        /** @var BillableContract */
        return $this->subscription->owner;
    }

    /**
     * Get the quantity.
     */
    public function quantity(): ?int
    {
        return $this->subscription->quantity;
    }

    /**
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool
    {
        return $this->subscription->hasPrice($price);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): static
    {
        $this->subscription->cancel();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): static
    {
        $this->subscription->cancelNow();

        return $this;
    }

    /**
     * Cancel the subscription immediately and invoice.
     */
    public function cancelNowAndInvoice(): static
    {
        $this->subscription->cancelNowAndInvoice();

        return $this;
    }

    /**
     * Resume the subscription.
     */
    public function resume(): static
    {
        $this->subscription->resume();

        return $this;
    }

    /**
     * Swap to a new price.
     *
     * @param  array<string, mixed>  $options
     */
    public function swap(string | array $prices, array $options = []): static
    {
        $this->subscription->swap($prices, $options);

        return $this;
    }

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity, ?string $price = null): static
    {
        $this->subscription->updateQuantity($quantity, $price);

        return $this;
    }

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1, ?string $price = null): static
    {
        $this->subscription->incrementQuantity($count, $price);

        return $this;
    }

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1, ?string $price = null): static
    {
        $this->subscription->decrementQuantity($count, $price);

        return $this;
    }

    /**
     * Get the underlying subscription model.
     */
    public function asGatewaySubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'gateway_id' => $this->gatewayId(),
            'gateway' => $this->gateway(),
            'type' => $this->type(),
            'quantity' => $this->quantity(),
            'valid' => $this->valid(),
            'active' => $this->active(),
            'on_trial' => $this->onTrial(),
            'canceled' => $this->canceled(),
            'ended' => $this->ended(),
            'on_grace_period' => $this->onGracePeriod(),
            'trial_ends_at' => $this->trialEndsAt()?->toIso8601String(),
            'ends_at' => $this->endsAt()?->toIso8601String(),
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
