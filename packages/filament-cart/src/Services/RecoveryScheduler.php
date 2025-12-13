<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service for scheduling cart recovery attempts.
 */
class RecoveryScheduler
{
    /**
     * Schedule recovery attempts for eligible carts based on campaign criteria.
     */
    public function scheduleForCampaign(RecoveryCampaign $campaign): int
    {
        if (! $campaign->isActive()) {
            return 0;
        }

        $eligibleCarts = $this->findEligibleCarts($campaign);
        $scheduled = 0;

        foreach ($eligibleCarts as $cart) {
            if ($this->scheduleAttemptForCart($campaign, $cart)) {
                $scheduled++;
            }
        }

        $campaign->update([
            'total_targeted' => $campaign->total_targeted + $scheduled,
            'last_run_at' => now(),
        ]);

        return $scheduled;
    }

    /**
     * Process all scheduled attempts that are due.
     *
     * @return array{processed: int, failed: int}
     */
    public function processScheduledAttempts(): array
    {
        $dueAttempts = RecoveryAttempt::query()
            ->where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit(100)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($dueAttempts as $attempt) {
            try {
                $this->queueAttempt($attempt);
                $processed++;
            } catch (Throwable $e) {
                $attempt->markAsFailed($e->getMessage());
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * Schedule the next attempt for a cart if applicable.
     */
    public function scheduleNextAttempt(RecoveryAttempt $previousAttempt): ?RecoveryAttempt
    {
        $campaign = $previousAttempt->campaign;
        $cart = $previousAttempt->cart;

        // Check if cart was recovered
        if ($cart && $cart->recovered_at !== null) {
            return null;
        }

        // Check if max attempts reached
        $attemptCount = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->where('cart_id', $previousAttempt->cart_id)
            ->count();

        if ($attemptCount >= $campaign->max_attempts) {
            return null;
        }

        return $this->createAttempt(
            $campaign,
            $cart,
            now()->addHours($campaign->attempt_interval_hours),
            $attemptCount + 1,
        );
    }

    /**
     * Cancel all scheduled attempts for a cart.
     */
    public function cancelAttemptsForCart(string $cartId): int
    {
        return RecoveryAttempt::query()
            ->where('cart_id', $cartId)
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Find carts eligible for a campaign.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Cart>
     */
    private function findEligibleCarts(RecoveryCampaign $campaign): \Illuminate\Database\Eloquent\Collection
    {
        $query = Cart::query()
            ->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->whereNull('checkout_completed_at');

        // Apply trigger delay
        $abandonedBefore = now()->subMinutes($campaign->trigger_delay_minutes);
        $query->where('checkout_abandoned_at', '<=', $abandonedBefore);

        // Apply cart value filters
        if ($campaign->min_cart_value_cents !== null) {
            $query->where('subtotal', '>=', $campaign->min_cart_value_cents);
        }

        if ($campaign->max_cart_value_cents !== null) {
            $query->where('subtotal', '<=', $campaign->max_cart_value_cents);
        }

        // Apply item count filters
        if ($campaign->min_items !== null) {
            $query->where('items_count', '>=', $campaign->min_items);
        }

        if ($campaign->max_items !== null) {
            $query->where('items_count', '<=', $campaign->max_items);
        }

        // Exclude carts already in this campaign
        $query->whereNotExists(function ($subquery) use ($campaign) {
            $prefix = config('filament-cart.database.table_prefix', 'cart_');
            $subquery->select(DB::raw(1))
                ->from($prefix . 'recovery_attempts')
                ->whereColumn($prefix . 'recovery_attempts.cart_id', 'carts.id')
                ->where($prefix . 'recovery_attempts.campaign_id', $campaign->id);
        });

        // Only get carts with contact info
        $query->where(function ($q) {
            $q->whereNotNull('email')
                ->orWhereRaw("JSON_EXTRACT(metadata, '$.email') IS NOT NULL");
        });

        return $query->limit(500)->get();
    }

    /**
     * Schedule an attempt for a specific cart.
     */
    private function scheduleAttemptForCart(RecoveryCampaign $campaign, Cart $cart): bool
    {
        $scheduledFor = now()->addMinutes(rand(1, 15)); // Add some randomization

        $attempt = $this->createAttempt($campaign, $cart, $scheduledFor, 1);

        return $attempt !== null;
    }

    /**
     * Create a recovery attempt.
     */
    private function createAttempt(
        RecoveryCampaign $campaign,
        Cart $cart,
        Carbon $scheduledFor,
        int $attemptNumber,
    ): ?RecoveryAttempt {
        $email = $cart->email ?? ($cart->metadata['email'] ?? null);
        $phone = $cart->phone ?? ($cart->metadata['phone'] ?? null);
        $name = $cart->customer_name ?? ($cart->metadata['customer_name'] ?? null);

        if ($email === null && $phone === null) {
            return null;
        }

        // Determine template (A/B testing)
        $templateId = $campaign->control_template_id;
        $isControl = true;
        $isVariant = false;

        if ($campaign->ab_testing_enabled && $campaign->variant_template_id) {
            $isVariant = rand(1, 100) <= $campaign->ab_test_split_percent;
            if ($isVariant) {
                $templateId = $campaign->variant_template_id;
                $isControl = false;
            }
        }

        // Generate discount code if applicable
        $discountCode = null;
        $discountValueCents = null;

        if ($campaign->offer_discount && $campaign->discount_value) {
            $discountCode = $this->generateDiscountCode($campaign, $cart);
            $discountValueCents = $campaign->discount_type === 'percentage'
                ? (int) ($cart->subtotal * $campaign->discount_value / 100)
                : $campaign->discount_value;
        }

        return RecoveryAttempt::create([
            'campaign_id' => $campaign->id,
            'cart_id' => $cart->id,
            'template_id' => $templateId,
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'recipient_name' => $name,
            'channel' => $campaign->strategy === 'multi_channel' ? 'email' : $campaign->strategy,
            'status' => 'scheduled',
            'attempt_number' => $attemptNumber,
            'is_control' => $isControl,
            'is_variant' => $isVariant,
            'discount_code' => $discountCode,
            'discount_value_cents' => $discountValueCents,
            'free_shipping_offered' => $campaign->offer_free_shipping,
            'offer_expires_at' => $campaign->urgency_hours ? now()->addHours($campaign->urgency_hours) : null,
            'cart_value_cents' => $cart->subtotal ?? 0,
            'cart_items_count' => $cart->items_count ?? 0,
            'scheduled_for' => $scheduledFor,
        ]);
    }

    /**
     * Queue an attempt for sending.
     */
    private function queueAttempt(RecoveryAttempt $attempt): void
    {
        $attempt->update([
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        // Dispatch to queue (handled by RecoveryDispatcher)
        dispatch(fn () => app(RecoveryDispatcher::class)->dispatch($attempt));
    }

    /**
     * Generate a unique discount code for this recovery.
     */
    private function generateDiscountCode(RecoveryCampaign $campaign, Cart $cart): string
    {
        return mb_strtoupper(sprintf(
            'RECOVER-%s-%s',
            mb_substr($campaign->id, 0, 4),
            Str::random(6),
        ));
    }
}
