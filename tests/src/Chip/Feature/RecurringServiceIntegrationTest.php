<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Events\RecurringScheduleCancelled;
use AIArmada\Chip\Events\RecurringScheduleCreated;
use AIArmada\Chip\Exceptions\NoRecurringTokenException;
use AIArmada\Chip\Models\RecurringSchedule;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\RecurringService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

describe('RecurringService', function (): void {
    beforeEach(function (): void {
        Event::fake();
        $this->chipService = Mockery::mock(ChipCollectService::class);
        $this->service = new RecurringService($this->chipService);
    });

    describe('createSchedule', function (): void {
        it('creates a recurring schedule with required parameters', function (): void {
            $schedule = $this->service->createSchedule(
                chipClientId: 'client-123',
                recurringToken: 'token-456',
                amountMinor: 10000,
                interval: RecurringInterval::Monthly,
            );

            expect($schedule)->toBeInstanceOf(RecurringSchedule::class);
            expect($schedule->chip_client_id)->toBe('client-123');
            expect($schedule->recurring_token_id)->toBe('token-456');
            expect($schedule->amount_minor)->toBe(10000);
            expect($schedule->interval)->toBe(RecurringInterval::Monthly);
            expect($schedule->status)->toBe(RecurringStatus::Active);
            expect($schedule->currency)->toBe('MYR');
            expect($schedule->interval_count)->toBe(1);
            expect($schedule->max_failures)->toBe(3);

            Event::assertDispatched(RecurringScheduleCreated::class);
        });

        it('creates schedule with custom parameters', function (): void {
            $firstChargeAt = Carbon::now()->addDays(7);
            $metadata = ['plan' => 'premium', 'feature' => 'unlimited'];

            $schedule = $this->service->createSchedule(
                chipClientId: 'client-123',
                recurringToken: 'token-456',
                amountMinor: 25000,
                interval: RecurringInterval::Weekly,
                intervalCount: 2,
                subscriber: null,
                currency: 'USD',
                firstChargeAt: $firstChargeAt,
                maxFailures: 5,
                metadata: $metadata,
            );

            expect($schedule->amount_minor)->toBe(25000);
            expect($schedule->currency)->toBe('USD');
            expect($schedule->interval)->toBe(RecurringInterval::Weekly);
            expect($schedule->interval_count)->toBe(2);
            expect($schedule->max_failures)->toBe(5);
            expect($schedule->metadata)->toBe($metadata);
        });

        it('calculates first charge for daily interval', function (): void {
            Carbon::setTestNow('2025-01-01 12:00:00');

            $schedule = $this->service->createSchedule(
                chipClientId: 'client-123',
                recurringToken: 'token-456',
                amountMinor: 10000,
                interval: RecurringInterval::Daily,
                intervalCount: 3,
            );

            expect($schedule->next_charge_at->format('Y-m-d'))->toBe('2025-01-04');

            Carbon::setTestNow();
        });

        it('calculates first charge for weekly interval', function (): void {
            Carbon::setTestNow('2025-01-01 12:00:00');

            $schedule = $this->service->createSchedule(
                chipClientId: 'client-123',
                recurringToken: 'token-456',
                amountMinor: 10000,
                interval: RecurringInterval::Weekly,
                intervalCount: 2,
            );

            expect($schedule->next_charge_at->format('Y-m-d'))->toBe('2025-01-15');

            Carbon::setTestNow();
        });

        it('calculates first charge for yearly interval', function (): void {
            Carbon::setTestNow('2025-01-01 12:00:00');

            $schedule = $this->service->createSchedule(
                chipClientId: 'client-123',
                recurringToken: 'token-456',
                amountMinor: 10000,
                interval: RecurringInterval::Yearly,
                intervalCount: 1,
            );

            expect($schedule->next_charge_at->format('Y-m-d'))->toBe('2026-01-01');

            Carbon::setTestNow();
        });
    });

    describe('createScheduleFromPurchase', function (): void {
        it('creates schedule from purchase data with recurring token', function (): void {
            $purchaseData = [
                'recurring_token' => 'token-789',
                'client_id' => 'client-123',
                'purchase' => [
                    'total' => 15000,
                    'currency' => 'MYR',
                ],
            ];

            $schedule = $this->service->createScheduleFromPurchase(
                purchaseData: $purchaseData,
                interval: RecurringInterval::Monthly,
            );

            expect($schedule->recurring_token_id)->toBe('token-789');
            expect($schedule->chip_client_id)->toBe('client-123');
            expect($schedule->amount_minor)->toBe(15000);

            Event::assertDispatched(RecurringScheduleCreated::class);
        });

        it('throws exception when purchase has no recurring token', function (): void {
            $purchaseData = [
                'client_id' => 'client-123',
                'purchase' => [
                    'total' => 15000,
                    'currency' => 'MYR',
                ],
            ];

            expect(fn () => $this->service->createScheduleFromPurchase(
                purchaseData: $purchaseData,
                interval: RecurringInterval::Monthly,
            ))->toThrow(NoRecurringTokenException::class);
        });

        it('uses client.id when client_id is missing', function (): void {
            $purchaseData = [
                'recurring_token' => 'token-789',
                'client' => ['id' => 'nested-client-id'],
                'purchase' => [
                    'total' => 15000,
                    'currency' => 'MYR',
                ],
            ];

            $schedule = $this->service->createScheduleFromPurchase(
                purchaseData: $purchaseData,
                interval: RecurringInterval::Monthly,
            );

            expect($schedule->chip_client_id)->toBe('nested-client-id');
        });
    });

    describe('cancel', function (): void {
        it('cancels an active schedule', function (): void {
            $schedule = RecurringSchedule::create([
                'chip_client_id' => 'client-123',
                'recurring_token_id' => 'token-456',
                'status' => RecurringStatus::Active,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->addMonth(),
                'max_failures' => 3,
            ]);

            $result = $this->service->cancel($schedule);

            expect($result->status)->toBe(RecurringStatus::Cancelled);
            expect($result->cancelled_at)->not->toBeNull();

            Event::assertDispatched(RecurringScheduleCancelled::class);
        });
    });

    describe('pause and resume', function (): void {
        it('pauses an active schedule', function (): void {
            $schedule = RecurringSchedule::create([
                'chip_client_id' => 'client-123',
                'recurring_token_id' => 'token-456',
                'status' => RecurringStatus::Active,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->addMonth(),
                'max_failures' => 3,
            ]);

            $result = $this->service->pause($schedule);

            expect($result->status)->toBe(RecurringStatus::Paused);
        });

        it('resumes a paused schedule', function (): void {
            $schedule = RecurringSchedule::create([
                'chip_client_id' => 'client-123',
                'recurring_token_id' => 'token-456',
                'status' => RecurringStatus::Paused,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->addMonth(),
                'max_failures' => 3,
            ]);

            $result = $this->service->resume($schedule);

            expect($result->status)->toBe(RecurringStatus::Active);
            expect($result->next_charge_at->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
        });
    });

    describe('updateAmount', function (): void {
        it('updates schedule amount', function (): void {
            $schedule = RecurringSchedule::create([
                'chip_client_id' => 'client-123',
                'recurring_token_id' => 'token-456',
                'status' => RecurringStatus::Active,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->addMonth(),
                'max_failures' => 3,
            ]);

            $result = $this->service->updateAmount($schedule, 25000);

            expect($result->amount_minor)->toBe(25000);
        });
    });

    describe('updateInterval', function (): void {
        it('updates schedule interval', function (): void {
            $schedule = RecurringSchedule::create([
                'chip_client_id' => 'client-123',
                'recurring_token_id' => 'token-456',
                'status' => RecurringStatus::Active,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->addMonth(),
                'max_failures' => 3,
            ]);

            $result = $this->service->updateInterval($schedule, RecurringInterval::Weekly, 2);

            expect($result->interval)->toBe(RecurringInterval::Weekly);
            expect($result->interval_count)->toBe(2);
        });
    });

    describe('getDueSchedules', function (): void {
        it('returns schedules due for processing', function (): void {
            // Create a due schedule
            RecurringSchedule::create([
                'chip_client_id' => 'client-1',
                'recurring_token_id' => 'token-1',
                'status' => RecurringStatus::Active,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->subHour(),
                'max_failures' => 3,
            ]);

            // Create a future schedule
            RecurringSchedule::create([
                'chip_client_id' => 'client-2',
                'recurring_token_id' => 'token-2',
                'status' => RecurringStatus::Active,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->addMonth(),
                'max_failures' => 3,
            ]);

            // Create a paused schedule (due but not active)
            RecurringSchedule::create([
                'chip_client_id' => 'client-3',
                'recurring_token_id' => 'token-3',
                'status' => RecurringStatus::Paused,
                'amount_minor' => 10000,
                'currency' => 'MYR',
                'interval' => RecurringInterval::Monthly,
                'interval_count' => 1,
                'next_charge_at' => now()->subHour(),
                'max_failures' => 3,
            ]);

            $due = $this->service->getDueSchedules();

            expect($due)->toHaveCount(1);
            expect($due->first()->chip_client_id)->toBe('client-1');
        });

        it('returns empty collection when no due schedules', function (): void {
            $due = $this->service->getDueSchedules();

            expect($due)->toHaveCount(0);
        });
    });
});
