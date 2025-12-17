<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

final class RealTimeActivityWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '10s';

    protected static ?string $heading = 'Real-Time Activity';

    public function table(Table $table): Table
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        return $table
            ->query(
                AffiliateConversion::query()
                    ->when(
                        (bool) config('affiliates.owner.enabled', false),
                        fn ($query) => $query->forOwner($owner),
                    )
                    ->with(['affiliate', 'attribution'])
                    ->latest('occurred_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime('H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable(),

                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order')
                    ->limit(15),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'qualified',
                        'success' => ['approved', 'paid'],
                        'danger' => 'rejected',
                    ]),
            ])
            ->paginated(false)
            ->defaultSort('occurred_at', 'desc');
    }
}
