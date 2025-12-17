<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

final class PayoutQueueWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected static ?string $heading = 'Pending Payouts';

    public function table(Table $table): Table
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        return $table
            ->query(
                AffiliatePayout::query()
                    ->when(
                        (bool) config('affiliates.owner.enabled', false),
                        fn ($query) => $query->whereHas('affiliate', function ($affiliateQuery) use ($owner): void {
                            if (! $owner) {
                                $affiliateQuery->whereNull('owner_type')->whereNull('owner_id');

                                return;
                            }

                            $affiliateQuery->where(function ($builder) use ($owner): void {
                                $builder->where('owner_type', $owner->getMorphClass())
                                    ->where('owner_id', $owner->getKey())
                                    ->orWhere(function ($inner): void {
                                        $inner->whereNull('owner_type')->whereNull('owner_id');
                                    });
                            });
                        }),
                    )
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
                    ->visible(fn ($record) => $record->status === PayoutStatus::Pending->value)
                    ->action(fn ($record) => $record->update(['status' => PayoutStatus::Processing->value])),

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
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        $pendingCount = AffiliatePayout::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->whereHas('affiliate', function ($affiliateQuery) use ($owner): void {
                    if (! $owner) {
                        $affiliateQuery->whereNull('owner_type')->whereNull('owner_id');

                        return;
                    }

                    $affiliateQuery->where(function ($builder) use ($owner): void {
                        $builder->where('owner_type', $owner->getMorphClass())
                            ->where('owner_id', $owner->getKey())
                            ->orWhere(function ($inner): void {
                                $inner->whereNull('owner_type')->whereNull('owner_id');
                            });
                    });
                }),
            )
            ->whereIn('status', [PayoutStatus::Pending->value, PayoutStatus::Processing->value])
            ->count();

        return "Pending Payouts ({$pendingCount})";
    }
}
