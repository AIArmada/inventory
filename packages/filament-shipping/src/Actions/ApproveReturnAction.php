<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Actions;

use AIArmada\Shipping\Models\ReturnAuthorization;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class ApproveReturnAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Return')
            ->modalDescription('Approve this return authorization request.')
            ->form([
                Forms\Components\Textarea::make('notes')
                    ->label('Approval Notes')
                    ->placeholder('Optional notes about this approval...')
                    ->rows(3),
            ])
            ->visible(fn (ReturnAuthorization $record): bool => $record->isPending())
            ->authorize(fn (ReturnAuthorization $record): bool => auth()->user()?->can('approve', $record) ?? false)
            ->action(function (ReturnAuthorization $record, array $data): void {
                $record->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                    'metadata' => array_merge($record->metadata ?? [], [
                        'approval_notes' => $data['notes'] ?? null,
                    ]),
                ]);

                Notification::make()
                    ->title('Return Approved')
                    ->body("RMA #{$record->rma_number} has been approved.")
                    ->success()
                    ->send();
            });
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'approve');
    }
}
