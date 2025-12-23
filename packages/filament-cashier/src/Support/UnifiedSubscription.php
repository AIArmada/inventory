<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Unified Subscription DTO - normalizes subscription data across gateways.
 *
 * @property-read string $id
 * @property-read string $gateway
 * @property-read string $userId
 * @property-read string $type
 * @property-read string $planId
 * @property-read int $amount
 * @property-read string $currency
 * @property-read int $quantity
 * @property-read SubscriptionStatus $status
 * @property-read Carbon|null $trialEndsAt
 * @property-read Carbon|null $endsAt
 * @property-read Carbon|null $nextBillingDate
 * @property-read Carbon $createdAt
 * @property-read Model $original
 */
final readonly class UnifiedSubscription
{
    public function __construct(
        public string $id,
        public string $gateway,
        public string $userId,
        public string $type,
        public string $planId,
        public int $amount,
        public string $currency,
        public int $quantity,
        public SubscriptionStatus $status,
        public ?Carbon $trialEndsAt,
        public ?Carbon $endsAt,
        public ?Carbon $nextBillingDate,
        public Carbon $createdAt,
        public Model $original,
    ) {}

    /**
     * Create from a Stripe subscription.
     */
    public static function fromStripe(Model $subscription): self
    {
        return new self(
            id: (string) $subscription->getKey(),
            gateway: 'stripe',
            userId: (string) $subscription->user_id,
            type: (string) $subscription->type,
            planId: (string) ($subscription->stripe_price ?? $subscription->name),
            amount: self::getStripeAmount($subscription),
            currency: 'USD',
            quantity: (int) ($subscription->quantity ?? 1),
            status: self::normalizeStripeStatus($subscription),
            trialEndsAt: $subscription->trial_ends_at,
            endsAt: $subscription->ends_at,
            nextBillingDate: self::calculateStripeNextBilling($subscription),
            createdAt: $subscription->created_at,
            original: $subscription,
        );
    }

    /**
     * Create from a CHIP subscription.
     */
    public static function fromChip(Model $subscription): self
    {
        return new self(
            id: (string) $subscription->getKey(),
            gateway: 'chip',
            userId: (string) $subscription->user_id,
            type: (string) $subscription->type,
            planId: (string) ($subscription->plan_id ?? $subscription->name),
            amount: self::getChipAmount($subscription),
            currency: 'MYR',
            quantity: (int) ($subscription->quantity ?? 1),
            status: self::normalizeChipStatus($subscription),
            trialEndsAt: $subscription->trial_ends_at,
            endsAt: $subscription->ends_at,
            nextBillingDate: $subscription->next_billing_at ?? null,
            createdAt: $subscription->created_at,
            original: $subscription,
        );
    }

    /**
     * Get formatted amount for display.
     */
    public function formattedAmount(): string
    {
        $symbol = match ($this->currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $this->currency . ' ',
        };

        return $symbol . number_format($this->amount / 100, 2);
    }

    /**
     * Get the billing cycle description.
     */
    public function billingCycle(): string
    {
        // Try to infer from plan ID naming conventions
        $planLower = mb_strtolower($this->planId);

        if (str_contains($planLower, 'annual') || str_contains($planLower, 'yearly')) {
            return __('filament-cashier::subscriptions.cycle.yearly');
        }

        if (str_contains($planLower, 'quarter')) {
            return __('filament-cashier::subscriptions.cycle.quarterly');
        }

        return __('filament-cashier::subscriptions.cycle.monthly');
    }

    /**
     * Check if this subscription requires attention.
     */
    public function needsAttention(): bool
    {
        return in_array($this->status, [
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Incomplete,
        ]);
    }

    /**
     * Get the gateway configuration.
     *
     * @return array{label: string, icon: string, color: string, dashboard_url: string}
     */
    public function gatewayConfig(): array
    {
        return app(GatewayDetector::class)->getGatewayConfig($this->gateway);
    }

    /**
     * Get external dashboard URL for this subscription.
     */
    public function externalDashboardUrl(): string
    {
        $baseUrl = $this->gatewayConfig()['dashboard_url'];

        return match ($this->gateway) {
            'stripe' => "{$baseUrl}/subscriptions/{$this->getExternalId()}",
            'chip' => "{$baseUrl}/subscriptions/{$this->getExternalId()}",
            default => $baseUrl,
        };
    }

    /**
     * Get the external/gateway-specific ID.
     */
    public function getExternalId(): string
    {
        return match ($this->gateway) {
            'stripe' => (string) ($this->original->stripe_id ?? $this->id),
            'chip' => (string) ($this->original->chip_id ?? $this->original->chip_subscription_id ?? $this->id),
            default => $this->id,
        };
    }

    private static function getStripeAmount(Model $subscription): int
    {
        if ($subscription->relationLoaded('items')) {
            $items = $subscription->getRelation('items');

            if (is_iterable($items)) {
                foreach ($items as $item) {
                    if (is_object($item) && isset($item->stripe_price)) {
                        return (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
                    }

                    break;
                }
            }

            return 0;
        }

        if (! method_exists($subscription, 'items')) {
            return 0;
        }

        $items = $subscription->items();

        if ($items instanceof Relation) {
            $item = $items->select(['quantity', 'unit_amount', 'stripe_price'])->first();

            if (is_object($item) && isset($item->stripe_price)) {
                return (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
            }

            return 0;
        }

        if (is_object($items) && method_exists($items, 'exists') && ! $items->exists()) {
            return 0;
        }

        if (is_object($items) && method_exists($items, 'first')) {
            $item = $items->first();

            if (is_object($item) && isset($item->stripe_price)) {
                return (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
            }
        }

        return 0;
    }

    private static function getChipAmount(Model $subscription): int
    {
        // CHIP stores amount directly or in items
        if (isset($subscription->amount)) {
            return (int) $subscription->amount;
        }

        if ($subscription->relationLoaded('items')) {
            $items = $subscription->getRelation('items');

            if (is_iterable($items)) {
                $total = 0;

                foreach ($items as $item) {
                    if (! is_object($item)) {
                        continue;
                    }

                    $total += (int) (($item->quantity ?? 0) * ($item->unit_amount ?? 0));
                }

                return $total;
            }

            return 0;
        }

        if (! method_exists($subscription, 'items')) {
            return 0;
        }

        $items = $subscription->items();

        if ($items instanceof Relation) {
            return (int) $items->sum(DB::raw('quantity * unit_amount'));
        }

        if (is_object($items) && method_exists($items, 'sum')) {
            return (int) $items->sum(DB::raw('quantity * unit_amount'));
        }

        if (is_object($items) && method_exists($items, 'exists') && ! $items->exists()) {
            return 0;
        }

        return 0;
    }

    private static function normalizeStripeStatus(Model $subscription): SubscriptionStatus
    {
        // Check Stripe-specific states
        if (method_exists($subscription, 'onGracePeriod') && $subscription->onGracePeriod()) {
            return SubscriptionStatus::OnGracePeriod;
        }

        if (method_exists($subscription, 'onTrial') && $subscription->onTrial()) {
            return SubscriptionStatus::OnTrial;
        }

        if (method_exists($subscription, 'canceled') && $subscription->canceled()) {
            return SubscriptionStatus::Canceled;
        }

        if (method_exists($subscription, 'pastDue') && $subscription->pastDue()) {
            return SubscriptionStatus::PastDue;
        }

        if (method_exists($subscription, 'active') && $subscription->active()) {
            return SubscriptionStatus::Active;
        }

        // Fallback to checking stripe_status
        $stripeStatus = $subscription->stripe_status ?? 'active';

        return match ($stripeStatus) {
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::OnTrial,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Canceled,
            'incomplete' => SubscriptionStatus::Incomplete,
            'incomplete_expired' => SubscriptionStatus::Expired,
            'paused' => SubscriptionStatus::Paused,
            default => SubscriptionStatus::Active,
        };
    }

    private static function normalizeChipStatus(Model $subscription): SubscriptionStatus
    {
        // Check CHIP-specific states
        if (method_exists($subscription, 'onGracePeriod') && $subscription->onGracePeriod()) {
            return SubscriptionStatus::OnGracePeriod;
        }

        if (method_exists($subscription, 'onTrial') && $subscription->onTrial()) {
            return SubscriptionStatus::OnTrial;
        }

        if (method_exists($subscription, 'canceled') && $subscription->canceled()) {
            return SubscriptionStatus::Canceled;
        }

        if (method_exists($subscription, 'active') && $subscription->active()) {
            return SubscriptionStatus::Active;
        }

        // Fallback to status column
        $status = $subscription->status ?? 'active';

        return match ($status) {
            'active' => SubscriptionStatus::Active,
            'trialing', 'trial' => SubscriptionStatus::OnTrial,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled', 'cancelled' => SubscriptionStatus::Canceled,
            'paused' => SubscriptionStatus::Paused,
            'expired' => SubscriptionStatus::Expired,
            default => SubscriptionStatus::Active,
        };
    }

    private static function calculateStripeNextBilling(Model $subscription): ?Carbon
    {
        // If subscription has ends_at, no next billing
        if ($subscription->ends_at !== null) {
            return null;
        }

        // If on trial, next billing is after trial
        if ($subscription->trial_ends_at !== null && $subscription->trial_ends_at->isFuture()) {
            return $subscription->trial_ends_at;
        }

        // Try to get from Stripe API metadata if available
        if (isset($subscription->current_period_end)) {
            return Carbon::createFromTimestamp($subscription->current_period_end);
        }

        return null;
    }
}
