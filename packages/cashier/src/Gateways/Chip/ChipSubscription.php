<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Gateways\Chip;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Contracts\SubscriptionItemContract;
use AIArmada\CashierChip\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Wrapper for CHIP subscription.
 *
 * This class wraps a CashierChip Subscription model and adapts it
 * to the unified SubscriptionContract interface.
 */
class ChipSubscription implements SubscriptionContract
{
    protected Subscription $subscription;

    /**
     * Create a new CHIP subscription wrapper.
     */
    public function __construct(mixed $subscription)
    {
        if (! $subscription instanceof Subscription) {
            throw new InvalidArgumentException('ChipSubscription expects an instance of ' . Subscription::class);
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
     * Note: CHIP subscriptions are managed locally, not on the gateway.
     */
    public function gatewayId(): string
    {
        return $this->subscription->chip_id ?? (string) $this->subscription->id;
    }

    /**
     * Get the gateway name.
     */
    public function gateway(): string
    {
        return 'chip';
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
     * Get the next billing date.
     */
    public function nextBillingAt(): ?CarbonInterface
    {
        return $this->subscription->next_billing_at;
    }

    /**
     * Get the subscription items.
     *
     * @return Collection<int, SubscriptionItemContract>
     */
    public function items(): Collection
    {
        return $this->subscription->items->map(fn ($item) => new ChipSubscriptionItem($item));
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
     * Get the recurring token.
     */
    public function recurringToken(): ?string
    {
        return $this->subscription->recurringToken();
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
     * Note: For CHIP, this is the same as cancelNow.
     */
    public function cancelNowAndInvoice(): static
    {
        $this->subscription->cancelNow();

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
     * Charge the subscription.
     */
    public function charge(?int $amount = null): mixed
    {
        return $this->subscription->charge($amount);
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
            'next_billing_at' => $this->nextBillingAt()?->toIso8601String(),
            'recurring_token' => $this->recurringToken(),
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
