<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Actions;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Action to record a payment against a document.
 */
final class RecordPaymentAction
{
    public static function make(): Action
    {
        return Action::make('record_payment')
            ->label('Record Payment')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Doc $record): bool => self::canRecordPayment($record))
            ->form(fn (Doc $record): array => self::getFormSchema($record))
            ->action(fn (Doc $record, array $data) => self::recordPayment($record, $data));
    }

    private static function canRecordPayment(Doc $record): bool
    {
        return in_array($record->status, [
            DocStatus::SENT,
            DocStatus::PENDING,
            DocStatus::OVERDUE,
            DocStatus::PARTIALLY_PAID,
        ], true);
    }

    private static function getTotalPaid(Doc $record): float
    {
        return (float) $record->payments()->sum('amount');
    }

    /**
     * @return array<int, Component>
     */
    private static function getFormSchema(Doc $record): array
    {
        $remaining = (float) $record->total - self::getTotalPaid($record);

        return [
            TextInput::make('amount')
                ->label('Payment Amount')
                ->required()
                ->numeric()
                ->prefix($record->currency)
                ->default($remaining)
                ->maxValue($remaining)
                ->helperText("Outstanding: {$record->currency} " . number_format($remaining, 2)),

            Select::make('payment_method')
                ->label('Payment Method')
                ->required()
                ->options(config('docs.payment_methods', [
                    'bank_transfer' => 'Bank Transfer',
                    'cash' => 'Cash',
                    'credit_card' => 'Credit Card',
                ]))
                ->default(array_key_first(config('docs.payment_methods', ['bank_transfer' => 'Bank Transfer']))),

            TextInput::make('reference')
                ->label('Reference / Transaction ID')
                ->placeholder('Bank reference, cheque number, etc.'),

            DateTimePicker::make('paid_at')
                ->label('Payment Date')
                ->required()
                ->default(CarbonImmutable::now())
                ->maxDate(CarbonImmutable::now()),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->placeholder('Additional notes about this payment'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function recordPayment(Doc $record, array $data): void
    {
        $amount = (float) $data['amount'];
        $remaining = (float) $record->total - self::getTotalPaid($record);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('Payment amount must be greater than 0.'),
            ]);
        }

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => __('Payment amount cannot exceed the outstanding balance.'),
            ]);
        }

        DocPayment::create([
            'doc_id' => $record->id,
            'owner_type' => $record->owner_type,
            'owner_id' => $record->owner_id,
            'amount' => $amount,
            'currency' => $record->currency,
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'],
            'notes' => $data['notes'] ?? null,
        ]);

        $newPaidAmount = self::getTotalPaid($record);

        if ($newPaidAmount >= (float) $record->total) {
            $record->status = DocStatus::PAID;
            $record->paid_at = $data['paid_at'];
        } else {
            $record->status = DocStatus::PARTIALLY_PAID;
        }

        $record->save();

        Notification::make()
            ->title('Payment Recorded')
            ->body("{$record->currency} " . number_format((float) $data['amount'], 2) . ' payment recorded successfully.')
            ->success()
            ->send();
    }
}
