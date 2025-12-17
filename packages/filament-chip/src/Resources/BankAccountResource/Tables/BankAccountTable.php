<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Tables;

use AIArmada\Chip\Services\ChipSendService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

final class BankAccountTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Account Holder')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('account_number')
                    ->label('Account Number')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('bank_code')
                    ->label('Bank')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record): string => $record->statusLabel())
                    ->color(fn ($record): string => $record->statusColor())
                    ->sortable(),

                IconColumn::make('is_debiting_account')
                    ->label('Debit')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_crediting_account')
                    ->label('Credit')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('group_id')
                    ->label('Group')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'verifying' => 'Verifying',
                        'rejected' => 'Rejected',
                        'disabled' => 'Disabled',
                    ])
                    ->label('Status'),

                SelectFilter::make('is_debiting_account')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->label('Debiting Account'),

                SelectFilter::make('is_crediting_account')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ])
                    ->label('Crediting Account'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),

                ActionGroup::make([
                    Action::make('verify')
                        ->label('Request Verification')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Request Verification')
                        ->modalDescription('This will submit the bank account for verification with CHIP. Continue?')
                        ->action(function ($record): void {
                            $service = app(ChipSendService::class);

                            try {
                                $service->updateBankAccount((string) $record->id, [
                                    'status' => 'verifying',
                                ]);
                                Notification::make()
                                    ->title('Verification requested')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Failed to request verification')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn ($record): bool => $record->status === 'pending'),

                    Action::make('disable')
                        ->label('Disable Account')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Disable Bank Account')
                        ->modalDescription('This will disable the bank account. It cannot be used for payouts until re-enabled.')
                        ->action(function ($record): void {
                            $service = app(ChipSendService::class);

                            try {
                                $service->deleteBankAccount((string) $record->id);
                                Notification::make()
                                    ->title('Bank account disabled')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Failed to disable account')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn ($record): bool => in_array($record->status, ['active', 'approved'], true)),
                ])
                    ->iconButton()
                    ->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No bank accounts')
            ->emptyStateDescription('Register bank accounts to send payouts via CHIP Send.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingLibrary)
            ->poll('30s');
    }
}
