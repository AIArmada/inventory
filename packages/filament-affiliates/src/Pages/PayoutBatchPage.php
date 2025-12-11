<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use UnitEnum;

final class PayoutBatchPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static string | UnitEnum | null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Payout Batch';

    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament-affiliates::pages.payout-batch';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliatePayout::query()
                    ->where('status', PayoutStatus::Pending)
                    ->with(['affiliate', 'payoutMethod'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record): string => Number::currency($state / 100, $record->currency ?? 'USD'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('payoutMethod.type')
                    ->label('Method')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn () => AffiliatePayout::distinct()->pluck('currency', 'currency')->toArray()),
            ])
            ->actions([
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (AffiliatePayout $record): void {
                        $service = app(AffiliatePayoutService::class);
                        $result = $service->processPayout($record);

                        if ($result->success) {
                            Notification::make()
                                ->success()
                                ->title('Payout processed')
                                ->body("Transaction ID: {$result->transactionId}")
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Payout failed')
                                ->body($result->error)
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(function (AffiliatePayout $record, array $data): void {
                        $record->update([
                            'status' => PayoutStatus::Failed,
                            'notes' => $data['reason'],
                            'failed_at' => now(),
                        ]);

                        Notification::make()
                            ->warning()
                            ->title('Payout rejected')
                            ->send();
                    }),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('batch_process')
                    ->label('Process All Selected')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $service = app(AffiliatePayoutService::class);
                        $success = 0;
                        $failed = 0;

                        foreach ($records as $record) {
                            $result = $service->processPayout($record);
                            if ($result->success) {
                                $success++;
                            } else {
                                $failed++;
                            }
                        }

                        Notification::make()
                            ->title('Batch processing complete')
                            ->body("Processed: {$success}, Failed: {$failed}")
                            ->send();
                    }),
            ]);
    }

    public function getViewData(): array
    {
        $pending = AffiliatePayout::where('status', PayoutStatus::Pending);

        return [
            'pendingCount' => $pending->count(),
            'pendingTotal' => $pending->sum('amount_minor'),
            'pendingByCurrency' => AffiliatePayout::where('status', PayoutStatus::Pending)
                ->selectRaw('currency, SUM(amount_minor) as total, COUNT(*) as count')
                ->groupBy('currency')
                ->get(),
        ];
    }
}
