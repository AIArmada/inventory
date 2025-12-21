<?php

declare(strict_types=1);

namespace AIArmada\Chip\Services;

use AIArmada\Chip\Enums\ChargeStatus;
use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Events\RecurringChargeRetryScheduled;
use AIArmada\Chip\Events\RecurringChargeSucceeded;
use AIArmada\Chip\Events\RecurringScheduleCancelled;
use AIArmada\Chip\Events\RecurringScheduleCreated;
use AIArmada\Chip\Events\RecurringScheduleFailed;
use AIArmada\Chip\Exceptions\ChipApiException;
use AIArmada\Chip\Exceptions\NoRecurringTokenException;
use AIArmada\Chip\Models\RecurringCharge;
use AIArmada\Chip\Models\RecurringSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * App-layer recurring payment service using Chip's token + charge APIs.
 */
class RecurringService
{
    public function __construct(
        private ChipCollectService $chip,
    ) {}

    /**
     * Create a recurring schedule after initial purchase with recurring token.
     */
    public function createSchedule(
        string $chipClientId,
        string $recurringToken,
        int $amountMinor,
        RecurringInterval $interval,
        int $intervalCount = 1,
        ?Model $subscriber = null,
        string $currency = 'MYR',
        ?Carbon $firstChargeAt = null,
        int $maxFailures = 3,
        ?array $metadata = null,
    ): RecurringSchedule {
        $schedule = RecurringSchedule::create([
            'chip_client_id' => $chipClientId,
            'recurring_token_id' => $recurringToken,
            'subscriber_type' => $subscriber?->getMorphClass(),
            'subscriber_id' => $subscriber?->getKey(),
            'status' => RecurringStatus::Active,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'next_charge_at' => $firstChargeAt ?? $this->calculateFirstCharge($interval, $intervalCount),
            'max_failures' => $maxFailures,
            'metadata' => $metadata,
        ]);

        event(new RecurringScheduleCreated($schedule));

        return $schedule;
    }

    /**
     * Create a schedule from an existing paid purchase that has a recurring token.
     *
     * @param  array<string, mixed>  $purchaseData  The purchase data from Chip API
     */
    public function createScheduleFromPurchase(
        array $purchaseData,
        RecurringInterval $interval,
        int $intervalCount = 1,
        ?Model $subscriber = null,
        ?Carbon $firstChargeAt = null,
    ): RecurringSchedule {
        $recurringToken = $purchaseData['recurring_token'] ?? null;

        if ($recurringToken === null) {
            throw new NoRecurringTokenException('Purchase does not have a recurring token');
        }

        $clientId = $purchaseData['client_id'] ?? $purchaseData['client']['id'] ?? null;
        $amount = $purchaseData['purchase']['total'] ?? $purchaseData['total'] ?? 0;
        $currency = $purchaseData['purchase']['currency'] ?? $purchaseData['currency'] ?? 'MYR';

        return $this->createSchedule(
            chipClientId: $clientId,
            recurringToken: $recurringToken,
            amountMinor: (int) $amount,
            interval: $interval,
            intervalCount: $intervalCount,
            subscriber: $subscriber,
            currency: $currency,
            firstChargeAt: $firstChargeAt,
        );
    }

    /**
     * Process a scheduled charge.
     */
    public function processCharge(RecurringSchedule $schedule): RecurringCharge
    {
        $charge = RecurringCharge::create([
            'schedule_id' => $schedule->id,
            'amount_minor' => $schedule->amount_minor,
            'currency' => $schedule->currency,
            'status' => ChargeStatus::Pending,
            'attempted_at' => now(),
        ]);

        try {
            // Create a new purchase using Chip API
            $purchase = $this->chip->purchase()
                ->clientId($schedule->chip_client_id)
                ->addProduct('Recurring Payment', $schedule->amount_minor)
                ->currency($schedule->currency)
                ->forceRecurring(true)
                ->create();

            // Charge using the saved token
            $result = $this->chip->chargePurchase($purchase->id, $schedule->recurring_token_id);

            $charge->update([
                'chip_purchase_id' => $result->id,
                'status' => ChargeStatus::Success,
            ]);

            $schedule->update([
                'last_charged_at' => now(),
                'next_charge_at' => $schedule->calculateNextChargeDate(),
                'failure_count' => 0,
            ]);

            event(new RecurringChargeSucceeded($schedule, $charge));

        } catch (ChipApiException $e) {
            $charge->update([
                'status' => ChargeStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            $this->handleFailure($schedule, $e);
        }

        return $charge;
    }

    /**
     * Cancel a recurring schedule.
     */
    public function cancel(RecurringSchedule $schedule): RecurringSchedule
    {
        $schedule->update([
            'status' => RecurringStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        event(new RecurringScheduleCancelled($schedule));

        return $schedule;
    }

    /**
     * Pause a recurring schedule.
     */
    public function pause(RecurringSchedule $schedule): RecurringSchedule
    {
        $schedule->update(['status' => RecurringStatus::Paused]);

        return $schedule;
    }

    /**
     * Resume a paused schedule.
     */
    public function resume(RecurringSchedule $schedule): RecurringSchedule
    {
        $schedule->update([
            'status' => RecurringStatus::Active,
            'next_charge_at' => now(),
        ]);

        return $schedule;
    }

    /**
     * Update schedule amount.
     */
    public function updateAmount(RecurringSchedule $schedule, int $amountMinor): RecurringSchedule
    {
        $schedule->update(['amount_minor' => $amountMinor]);

        return $schedule;
    }

    /**
     * Update schedule interval.
     */
    public function updateInterval(
        RecurringSchedule $schedule,
        RecurringInterval $interval,
        int $intervalCount = 1
    ): RecurringSchedule {
        $schedule->update([
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'next_charge_at' => $schedule->calculateNextChargeDate(),
        ]);

        return $schedule;
    }

    /**
     * Get all due schedules ready for processing.
     *
     * @return Collection<int, RecurringSchedule>
     */
    public function getDueSchedules(): Collection
    {
        return RecurringSchedule::query()
            ->where('status', RecurringStatus::Active->value)
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', now())
            ->get();
    }

    /**
     * Process all due schedules.
     *
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function processAllDue(): array
    {
        $due = $this->getDueSchedules();
        $succeeded = 0;
        $failed = 0;

        foreach ($due as $schedule) {
            try {
                $charge = $this->processCharge($schedule);
                if ($charge->isSuccess()) {
                    $succeeded++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;
                report($e);
            }
        }

        return [
            'processed' => $due->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    /**
     * Handle charge failure with retry logic.
     */
    private function handleFailure(RecurringSchedule $schedule, ChipApiException $e): void
    {
        $schedule->increment('failure_count');

        if ($schedule->failure_count >= $schedule->max_failures) {
            $schedule->update(['status' => RecurringStatus::Failed]);
            event(new RecurringScheduleFailed($schedule));
        } else {
            // Exponential backoff: 2^failure_count * 24 hours
            $retryDelayHours = (int) pow(2, $schedule->failure_count) * 24;
            $schedule->update([
                'next_charge_at' => now()->addHours($retryDelayHours),
            ]);
            event(new RecurringChargeRetryScheduled($schedule, $retryDelayHours));
        }
    }

    private function calculateFirstCharge(RecurringInterval $interval, int $count): Carbon
    {
        return match ($interval) {
            RecurringInterval::Daily => now()->addDays($count),
            RecurringInterval::Weekly => now()->addWeeks($count),
            RecurringInterval::Monthly => now()->addMonths($count),
            RecurringInterval::Yearly => now()->addYears($count),
        };
    }
}
