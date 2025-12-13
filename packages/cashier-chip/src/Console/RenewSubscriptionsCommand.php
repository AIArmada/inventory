<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Console;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Subscription;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Command to process subscription renewals for CHIP.
 *
 * Since CHIP doesn't have native subscriptions, this command must be
 * scheduled to run periodically (e.g., daily or hourly) to process
 * subscription renewals by charging stored recurring tokens.
 *
 * Add to your scheduler in app/Console/Kernel.php:
 *
 *     $schedule->command('cashier-chip:renew-subscriptions')->hourly();
 */
class RenewSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashier-chip:renew-subscriptions 
                            {--dry-run : Show what would be renewed without actually charging}
                            {--grace-hours=0 : Hours of grace period before considering subscription due}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process CHIP subscription renewals by charging recurring tokens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $graceHours = (int) $this->option('grace-hours');

        $this->info('Processing CHIP subscription renewals...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No charges will be made');
        }

        // Get all active subscriptions due for renewal
        $dueDate = now()->subHours($graceHours);

        $subscriptions = Subscription::query()
            ->active()
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', $dueDate)
            ->with('owner')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions due for renewal.');

            return self::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscription(s) due for renewal.");

        $renewed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $owner = $subscription->owner;

            if (! $owner) {
                $this->warn("Subscription {$subscription->id} has no owner, skipping.");

                continue;
            }

            /** @var Model&BillableContract $owner */
            $this->line("Processing: {$subscription->type} for {$owner->chipEmail()}");

            if ($dryRun) {
                $this->info('  → Would charge: ' . $this->formatAmount($subscription));
                $renewed++;

                continue;
            }

            try {
                $payment = $this->chargeSubscription($subscription);

                if ($payment && $payment->isSucceeded()) {
                    $this->info('  ✓ Renewed successfully');
                    $renewed++;

                    SubscriptionRenewed::dispatch($subscription, $payment);
                } else {
                    $this->error('  ✗ Payment requires action or is pending');
                    $this->markAsPastDue($subscription);
                    $failed++;
                }
            } catch (Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                Log::error('Subscription renewal failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                $this->markAsPastDue($subscription);
                $failed++;

                SubscriptionRenewalFailed::dispatch($subscription, $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Renewal complete: {$renewed} renewed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Charge the subscription using the owner's recurring token.
     */
    protected function chargeSubscription(Subscription $subscription): mixed
    {
        $owner = $subscription->owner;

        if (! $owner) {
            throw new RuntimeException('Subscription has no owner');
        }

        /** @var Model&BillableContract $owner */
        $recurringTokenId = $owner->defaultPaymentMethod()?->id();

        if (! $recurringTokenId) {
            throw new RuntimeException('No payment method available for renewal');
        }

        // Calculate amount from subscription items or stored price
        $amount = $subscription->calculateSubscriptionAmount();

        if ($amount <= 0) {
            throw new RuntimeException('Invalid subscription amount');
        }

        // Charge using the recurring token
        return $owner->charge($amount, $recurringTokenId, [
            'product_name' => "Subscription: {$subscription->type}",
            'reference' => "Subscription {$subscription->type} - Renewal {$subscription->next_billing_at?->format('Y-m-d')}",
            'metadata' => [
                'subscription_id' => $subscription->id,
                'subscription_type' => $subscription->type,
                'renewal' => true,
            ],
        ]);
    }

    /**
     * Mark subscription as past due after failed payment.
     */
    protected function markAsPastDue(Subscription $subscription): void
    {
        $subscription->forceFill([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ])->save();
    }

    /**
     * Format the subscription amount for display.
     */
    protected function formatAmount(Subscription $subscription): string
    {
        $amount = $subscription->calculateSubscriptionAmount();
        $currency = $subscription->owner?->preferredCurrency() ?? 'MYR';

        return Cashier::formatAmount($amount, $currency);
    }
}
