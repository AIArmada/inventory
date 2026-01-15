<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Console;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Subscription;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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

        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            $owners = Subscription::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            if ($owners->isEmpty()) {
                $result = $this->processRenewals((bool) $dryRun, $graceHours);

                $this->newLine();
                $this->info("Renewal complete: {$result['renewed']} renewed, {$result['failed']} failed.");

                return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
            }

            $totals = [
                'renewed' => 0,
                'failed' => 0,
            ];

            foreach ($owners as $row) {
                $owner = $this->resolveOwnerFromRow($row);

                $result = OwnerContext::withOwner($owner, fn (): array => $this->processRenewals((bool) $dryRun, $graceHours));

                $totals['renewed'] += $result['renewed'];
                $totals['failed'] += $result['failed'];
            }

            $this->newLine();
            $this->info("Renewal complete: {$totals['renewed']} renewed, {$totals['failed']} failed.");

            return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $result = $this->processRenewals((bool) $dryRun, $graceHours);

        $this->newLine();
        $this->info("Renewal complete: {$result['renewed']} renewed, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{renewed: int, failed: int}
     */
    protected function processRenewals(bool $dryRun, int $graceHours): array
    {
        $dueDate = now()->subHours($graceHours);

        $query = Subscription::query();
        $query = (new Subscription)->scopeForOwner($query);

        $subscriptions = $query
            ->whereActive()
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', $dueDate)
            ->with('customer')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions due for renewal.');

            return [
                'renewed' => 0,
                'failed' => 0,
            ];
        }

        $this->info("Found {$subscriptions->count()} subscription(s) due for renewal.");

        $renewed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $customer = $subscription->customer;

            if (! $customer) {
                $this->warn("Subscription {$subscription->id} has no owner, skipping.");

                continue;
            }

            /** @var Model&BillableContract $customer */
            $this->line("Processing: {$subscription->type} for {$customer->chipEmail()}");

            if ($dryRun) {
                $this->info('  → Would charge: ' . $this->formatAmount($subscription));
                $renewed++;

                continue;
            }

            try {
                $payment = DB::transaction(function () use ($subscription) {
                    $payment = $this->chargeSubscription($subscription);

                    if ($payment && $payment->isSucceeded()) {
                        $subscription->forceFill([
                            'chip_status' => Subscription::STATUS_ACTIVE,
                            'next_billing_at' => now()->add(
                                $subscription->billing_interval ?? 'month',
                                $subscription->billing_interval_count ?? 1
                            ),
                        ])->save();
                    }

                    return $payment;
                });

                if ($payment && $payment->isSucceeded()) {
                    $this->info('  ✓ Renewed successfully');
                    $renewed++;

                    SubscriptionRenewed::dispatch($subscription, $payment);
                } else {
                    $this->error('  ✗ Payment requires action or is pending');
                    $this->markAsPastDue($subscription);
                    $failed++;
                }
            } catch (Throwable $e) {
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

        return [
            'renewed' => $renewed,
            'failed' => $failed,
        ];
    }

    protected function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }

    /**
     * Charge the subscription using the owner's recurring token.
     */
    protected function chargeSubscription(Subscription $subscription): mixed
    {
        $customer = $subscription->customer;

        if (! $customer) {
            throw new RuntimeException('Subscription has no owner');
        }

        /** @var Model&BillableContract $customer */
        $recurringTokenId = $customer->defaultPaymentMethod()?->id();

        if (! $recurringTokenId) {
            throw new RuntimeException('No payment method available for renewal');
        }

        // Calculate amount from subscription items or stored price
        $amount = $subscription->calculateSubscriptionAmount();

        if ($amount <= 0) {
            throw new RuntimeException('Invalid subscription amount');
        }

        // Charge using the recurring token
        return $customer->charge($amount, $recurringTokenId, [
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
        $currency = $subscription->customer?->preferredCurrency() ?? 'MYR';

        return Cashier::formatAmount($amount, $currency);
    }
}
