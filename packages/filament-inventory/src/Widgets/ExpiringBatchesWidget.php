<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryBatch;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Route;

final class ExpiringBatchesWidget extends TableWidget
{
    protected static ?int $sort = 30;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Expiring Batches';

    public static function canView(): bool
    {
        return config('filament-inventory.features.expiring_batches_widget', true);
    }

    public function table(Table $table): Table
    {
        $query = InventoryBatch::query()
            ->allocatable()
            ->expiringSoon(config('filament-inventory.tables.expiry_warning_days', 30))
            ->with(['location'])
            ->orderBy('expires_at')
            ->limit(10);

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('No location'),

                TextColumn::make('available_quantity')
                    ->label('Available')
                    ->numeric()
                    ->alignEnd(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (InventoryBatch $record): string => match (true) {
                        $record->is_expired => 'danger',
                        ($record->days_until_expiry ?? 999) <= 7 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('daysUntilExpiry')
                    ->label('Days Left')
                    ->state(
                        fn (InventoryBatch $record): string => $record->is_expired
                        ? 'Expired'
                        : ($record->days_until_expiry ?? 0) . ' days'
                    )
                    ->badge()
                    ->color(fn (InventoryBatch $record): string => match (true) {
                        $record->is_expired => 'danger',
                        ($record->days_until_expiry ?? 999) <= 7 => 'warning',
                        ($record->days_until_expiry ?? 999) <= 14 => 'info',
                        default => 'success',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InventoryBatch $record): string => $record->getStatusEnum()->color()),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (): bool => Route::has('filament.admin.resources.inventory-batches.view'))
                    ->url(fn (InventoryBatch $record): string => route('filament.admin.resources.inventory-batches.view', $record)),
            ])
            ->emptyStateHeading('No Expiring Batches')
            ->emptyStateDescription('No batches are expiring within the warning period.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
