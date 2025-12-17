<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\ReturnAuthorization;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class RejectReturnAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reject Return')
            ->modalDescription('Reject this return authorization request.')
            ->form([
                Forms\Components\Textarea::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->placeholder('Please provide a reason for rejection...')
                    ->required()
                    ->rows(3),
            ])
            ->visible(fn (ReturnAuthorization $record): bool => $record->isPending())
            ->authorize(fn (ReturnAuthorization $record): bool => auth()->user()?->can('reject', $record) ?? false)
            ->action(function (ReturnAuthorization $record, array $data): void {
                $record->update([
                    'status' => 'rejected',
                    'metadata' => array_merge($record->metadata ?? [], [
                        'rejection_reason' => $data['rejection_reason'],
                        'rejected_at' => now()->toDateTimeString(),
                        'rejected_by' => auth()->id(),
                    ]),
                ]);

                Notification::make()
                    ->title('Return Rejected')
                    ->body("RMA #{$record->rma_number} has been rejected.")
                    ->warning()
                    ->send();
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'reject');
    }
}
