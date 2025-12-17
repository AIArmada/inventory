<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\CompanyStatementResource\Tables;

use AIArmada\Chip\Services\ChipCollectService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Throwable;

final class CompanyStatementTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ucfirst($state ?? 'unknown'))
                    ->color(fn ($record): string => $record->statusColor())
                    ->sortable(),

                IconColumn::make('is_test')
                    ->label('Test')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon('heroicon-o-check-badge')
                    ->trueColor('warning')
                    ->falseColor('success'),

                TextColumn::make('began_on')
                    ->label('Period Start')
                    ->formatStateUsing(fn ($record): string => $record->beganOn?->format('Y-m-d') ?? 'N/A')
                    ->sortable(),

                TextColumn::make('finished_on')
                    ->label('Period End')
                    ->formatStateUsing(fn ($record): string => $record->finishedOn?->format('Y-m-d') ?? 'N/A')
                    ->sortable(),

                TextColumn::make('created_on')
                    ->label('Requested')
                    ->formatStateUsing(fn ($record): string => $record->createdOn?->format('Y-m-d H:i') ?? 'N/A')
                    ->sortable(),

                TextColumn::make('updated_on')
                    ->label('Last Updated')
                    ->formatStateUsing(fn ($record): string => $record->updatedOn?->format('Y-m-d H:i') ?? 'N/A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_on', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'ready' => 'Ready',
                        'queued' => 'Queued',
                        'processing' => 'Processing',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                    ])
                    ->label('Status'),

                TernaryFilter::make('is_test')
                    ->label('Test Mode')
                    ->trueLabel('Test only')
                    ->falseLabel('Live only')
                    ->placeholder('All'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),

                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function ($record): void {
                        $service = app(ChipCollectService::class);

                        try {
                            $statement = $service->getCompanyStatement($record->id);
                            $downloadUrl = $statement->download_url ?? null;

                            if ($downloadUrl) {
                                redirect()->away($downloadUrl);
                            } else {
                                Notification::make()
                                    ->title('Download not available')
                                    ->body('Statement is not ready for download yet.')
                                    ->warning()
                                    ->send();
                            }
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Failed to fetch statement')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => in_array($record->status, ['completed', 'ready'], true)),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Statement Request')
                    ->modalDescription('Are you sure you want to cancel this statement request?')
                    ->action(function ($record): void {
                        $service = app(ChipCollectService::class);

                        try {
                            $service->cancelCompanyStatement($record->id);
                            Notification::make()
                                ->title('Statement request cancelled')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Failed to cancel statement')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => in_array($record->status, ['queued', 'processing'], true)),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No company statements')
            ->emptyStateDescription('Company statements from CHIP will appear here.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->poll('30s');
    }
}
