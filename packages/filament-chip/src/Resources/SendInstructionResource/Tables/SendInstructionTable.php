<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Tables;

use AIArmada\Chip\Models\SendInstruction;
use AIArmada\Chip\Services\ChipSendService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

final class SendInstructionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('bankAccount.name')
                    ->label('Recipient')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('bankAccount.account_number')
                    ->label('Account')
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (?string $state): string => 'MYR ' . number_format((float) ($state ?? 0), 2))
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),

                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed', 'processed' => 'success',
                        'received', 'queued', 'verifying' => 'warning',
                        'failed', 'cancelled', 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => (string) str($state ?? 'unknown')->headline()),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        'received' => 'Received',
                        'queued' => 'Queued',
                        'verifying' => 'Verifying',
                        'completed' => 'Completed',
                        'processed' => 'Processed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Payout')
                    ->modalDescription('Are you sure you want to cancel this payout? This action cannot be undone.')
                    ->visible(fn (SendInstruction $record): bool => in_array($record->state, ['received', 'queued'], true))
                    ->action(function (SendInstruction $record): void {
                        try {
                            app(ChipSendService::class)->cancelSendInstruction((string) $record->id);

                            Notification::make()
                                ->title('Payout cancelled')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Failed to cancel payout')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('resend_webhook')
                    ->label('Resend Webhook')
                    ->icon(Heroicon::ArrowPath)
                    ->color('gray')
                    ->action(function (SendInstruction $record): void {
                        try {
                            app(ChipSendService::class)->resendSendInstructionWebhook((string) $record->id);

                            Notification::make()
                                ->title('Webhook resent')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Failed to resend webhook')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-chip.polling_interval', '45s'));
    }
}
