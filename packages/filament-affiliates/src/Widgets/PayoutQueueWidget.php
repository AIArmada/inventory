<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Gate;

final class PayoutQueueWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected static ?string $heading = 'Pending Payouts';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                    ->with('affiliate')
                    ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Processing->value])
                    ->orderBy('scheduled_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => PayoutStatus::Pending->value,
                        'info' => PayoutStatus::Processing->value,
                    ]),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->counts('conversions'),
            ])
            ->actions([
                Action::make('process')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.payout.update') ?? false)
                    ->visible(fn ($record) => $record->status === PayoutStatus::Pending->value)
                    ->action(function (AffiliatePayout $record): void {
                        Gate::authorize('update', $record);

                        $payout = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $payout->update(['status' => PayoutStatus::Processing->value]);
                    }),

                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.affiliate-payouts.view', $record)),
            ])
            ->paginated(false)
            ->emptyStateHeading('No pending payouts')
            ->emptyStateDescription('All payouts have been processed.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    protected function getTableHeading(): ?string
    {
        $pendingCount = OwnerScopedQuery::throughAffiliate(AffiliatePayout::query())
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Processing->value])
            ->count();

        return "Pending Payouts ({$pendingCount})";
    }
}
