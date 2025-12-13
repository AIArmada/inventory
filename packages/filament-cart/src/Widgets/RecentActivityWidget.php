<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Services\CartMonitor;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Recent cart activity feed widget.
 */
class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Activity';

    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $monitor = app(CartMonitor::class);

        return $table
            ->query(fn () => $this->getActivityQuery())
            ->columns([
                Tables\Columns\TextColumn::make('session_id')
                    ->label('Session')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->session_id),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'checkout' => 'success',
                        'active' => 'info',
                        'abandoned' => 'warning',
                        'completed' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Value')
                    ->money('USD', divideBy: 100),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10]);
    }

    private function getActivityQuery()
    {
        $snapshotsTable = $this->getSnapshotsTable();

        return \Illuminate\Support\Facades\DB::table($snapshotsTable)
            ->select('id', 'session_id', 'status', 'items_count', 'total_cents', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(50);
    }

    private function getSnapshotsTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['snapshots'] ?? $prefix . 'snapshots';
    }
}
