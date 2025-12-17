<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use Exception;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class BulkPayoutAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Process Payouts');
        $this->icon('heroicon-o-banknotes');
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading('Process Selected Payouts');
        $this->modalDescription('Are you sure you want to process these payouts? This will initiate payment transfers.');

        $this->action(function (Collection $records): void {
            $factory = app(PayoutProcessorFactory::class);
            $processed = 0;
            $failed = 0;

            foreach ($records as $payout) {
                if ($payout->status !== PayoutStatus::Pending) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($payout, $factory, &$processed, &$failed): void {
                        $payout->update(['status' => PayoutStatus::Processing->value]);

                        $payoutMethod = $payout->affiliate->payoutMethods()
                            ->where('is_default', true)
                            ->first();

                        if (! $payoutMethod) {
                            $payout->update(['status' => PayoutStatus::Failed->value]);
                            $payout->events()->create([
                                'from_status' => PayoutStatus::Processing->value,
                                'to_status' => PayoutStatus::Failed->value,
                                'notes' => 'No default payout method configured',
                            ]);
                            $failed++;

                            return;
                        }

                        $processor = $factory->make($payoutMethod->type->value);
                        $result = $processor->process($payout);

                        if ($result->success) {
                            $payout->update([
                                'status' => PayoutStatus::Completed->value,
                                'paid_at' => now(),
                                'metadata' => array_merge(
                                    $payout->metadata ?? [],
                                    $result->metadata,
                                    ['external_reference' => $result->externalReference],
                                ),
                            ]);

                            $payout->events()->create([
                                'from_status' => PayoutStatus::Processing->value,
                                'to_status' => PayoutStatus::Completed->value,
                                'notes' => 'Payout processed successfully',
                            ]);

                            $processed++;
                        } else {
                            $payout->update(['status' => PayoutStatus::Failed->value]);
                            $payout->events()->create([
                                'from_status' => PayoutStatus::Processing->value,
                                'to_status' => PayoutStatus::Failed->value,
                                'notes' => $result->failureReason,
                            ]);
                            $failed++;
                        }
                    });
                } catch (Exception $e) {
                    $fromStatus = $payout->status instanceof PayoutStatus
                        ? $payout->status->value
                        : (string) $payout->status;

                    $payout->update(['status' => PayoutStatus::Failed->value]);
                    $payout->events()->create([
                        'from_status' => $fromStatus,
                        'to_status' => PayoutStatus::Failed->value,
                        'notes' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }

            if ($processed > 0) {
                $this->success();
            }

            $this->sendSuccessNotification();
        });

        $this->deselectRecordsAfterCompletion();
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_process_payouts';
    }
}
