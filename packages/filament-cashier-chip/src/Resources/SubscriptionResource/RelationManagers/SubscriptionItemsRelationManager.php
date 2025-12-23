<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\SubscriptionResource\RelationManagers;

use AIArmada\CashierChip\SubscriptionItem;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SubscriptionItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'chip_price';

    protected static ?string $title = 'Subscription Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('chip_id')
                    ->label('Item ID')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('chip_product')
                    ->label('Product')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('chip_price')
                    ->label('Price')
                    ->badge()
                    ->color('success')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('unit_amount')
                    ->label('Unit Amount')
                    ->formatStateUsing(fn (?int $state): ?string => $state !== null ? self::formatAmount($state) : null)
                    ->placeholder('—'),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn (SubscriptionItem $record): string => self::formatAmount($record->totalAmount()))
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([])
            ->actions([
                Action::make('increment')
                    ->label('Increment')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SubscriptionItem $record): void {
                        $record->incrementQuantity();

                        Notification::make()
                            ->title('Quantity Incremented')
                            ->body("Quantity increased to {$record->quantity}")
                            ->success()
                            ->send();
                    }),

                Action::make('decrement')
                    ->label('Decrement')
                    ->icon('heroicon-o-minus')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SubscriptionItem $record): bool => ($record->quantity ?? 1) > 1)
                    ->action(function (SubscriptionItem $record): void {
                        $record->decrementQuantity();

                        Notification::make()
                            ->title('Quantity Decremented')
                            ->body("Quantity decreased to {$record->quantity}")
                            ->success()
                            ->send();
                    }),

                Action::make('update_quantity')
                    ->label('Set Quantity')
                    ->icon('heroicon-o-calculator')
                    ->form([
                        TextInput::make('quantity')
                            ->label('New Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(fn (SubscriptionItem $record): int => $record->quantity ?? 1),
                    ])
                    ->action(function (SubscriptionItem $record, array $data): void {
                        $record->updateQuantity((int) $data['quantity']);

                        Notification::make()
                            ->title('Quantity Updated')
                            ->body("Quantity set to {$record->quantity}")
                            ->success()
                            ->send();
                    }),

                Action::make('swap_price')
                    ->label('Swap Price')
                    ->icon('heroicon-o-arrows-right-left')
                    ->form([
                        TextInput::make('price')
                            ->label('New Price ID')
                            ->required()
                            ->placeholder('price_xxx'),

                        TextInput::make('unit_amount')
                            ->label('Unit Amount (cents)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Optional'),
                    ])
                    ->action(function (SubscriptionItem $record, array $data): void {
                        $options = [];
                        if (array_key_exists('unit_amount', $data) && $data['unit_amount'] !== null) {
                            $options['unit_amount'] = (int) $data['unit_amount'];
                        }

                        $record->swap($data['price'], $options);

                        Notification::make()
                            ->title('Price Swapped')
                            ->body("Item switched to price: {$data['price']}")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No Subscription Items')
            ->emptyStateDescription('This subscription has no items configured.');
    }

    private static function formatAmount(int $amount): string
    {
        $currency = config('cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);
        $value = $amount / 100;

        return mb_strtoupper($currency) . ' ' . number_format($value, $precision, '.', ',');
    }
}
