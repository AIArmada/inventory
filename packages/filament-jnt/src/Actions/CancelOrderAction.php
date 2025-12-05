<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Actions;

use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class CancelOrderAction
{
    public static function make(): Action
    {
        return Action::make('cancelOrder')
            ->label('Cancel Order')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel J&T Order')
            ->modalDescription('This will cancel the order with J&T Express. This action cannot be undone.')
            ->modalSubmitActionLabel('Cancel Order')
            ->form([
                Select::make('reason')
                    ->label('Cancellation Reason')
                    ->options(self::getReasonOptions())
                    ->required()
                    ->searchable()
                    ->helperText('Select the reason for cancelling this order.'),
                Textarea::make('custom_reason')
                    ->label('Additional Details')
                    ->placeholder('Provide additional context if needed...')
                    ->rows(2)
                    ->visible(fn (callable $get): bool => $get('reason') === CancellationReason::OTHER->value),
            ])
            ->action(function (JntOrder $record, array $data): void {
                try {
                    $jntService = app(JntExpressService::class);

                    $reason = CancellationReason::tryFrom($data['reason']) ?? $data['reason'];

                    if ($reason === CancellationReason::OTHER && ! empty($data['custom_reason'])) {
                        $reasonString = $data['custom_reason'];
                    } else {
                        $reasonString = $reason instanceof CancellationReason ? $reason->getDescription() : (string) $reason;
                    }

                    $jntService->cancelOrder(
                        orderId: $record->order_id,
                        reason: $reasonString,
                        trackingNumber: $record->tracking_number
                    );

                    $record->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'cancellation_reason' => $reasonString,
                    ]);

                    Notification::make()
                        ->title('Order Cancelled')
                        ->body("Order {$record->order_id} has been cancelled successfully.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Cancellation Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (JntOrder $record): bool => self::canCancel($record));
    }

    /**
     * @return array<string, string>
     */
    private static function getReasonOptions(): array
    {
        $options = [];

        $options['Customer-Initiated'] = [];
        foreach (CancellationReason::customerInitiated() as $reason) {
            $options['Customer-Initiated'][$reason->value] = $reason->getDescription();
        }

        $options['Merchant-Initiated'] = [];
        foreach (CancellationReason::merchantInitiated() as $reason) {
            $options['Merchant-Initiated'][$reason->value] = $reason->getDescription();
        }

        $options['Delivery Issues'] = [];
        foreach (CancellationReason::deliveryIssues() as $reason) {
            $options['Delivery Issues'][$reason->value] = $reason->getDescription();
        }

        $options['Payment Issues'] = [];
        foreach (CancellationReason::paymentIssues() as $reason) {
            $options['Payment Issues'][$reason->value] = $reason->getDescription();
        }

        $options['Other'] = [
            CancellationReason::SYSTEM_ERROR->value => CancellationReason::SYSTEM_ERROR->getDescription(),
            CancellationReason::OTHER->value => CancellationReason::OTHER->getDescription(),
        ];

        return $options;
    }

    private static function canCancel(JntOrder $record): bool
    {
        $nonCancellableStatuses = ['delivered', 'cancelled', 'returned'];

        return ! in_array($record->status, $nonCancellableStatuses, true);
    }
}
