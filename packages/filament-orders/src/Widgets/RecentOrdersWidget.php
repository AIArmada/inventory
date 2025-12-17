<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->forOwner()
                    ->with(['items'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'Unknown')
                    ->color(fn ($state) => match ($state?->color() ?? 'gray') {
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                        'info' => 'info',
                        'primary' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
