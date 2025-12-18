<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Tables;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),

                TextColumn::make('instance')
                    ->label('Instance')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'default' ? 'primary' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->alignEnd()
                    ->money(fn (Cart $record): string => /** @phpstan-ignore property.notFound */ $record->currency, divideBy: 100)
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->money(fn (Cart $record): string => /** @phpstan-ignore property.notFound */ $record->currency, divideBy: 100)
                    ->sortable(),

                TextColumn::make('savings')
                    ->label('Savings')
                    ->alignEnd()
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->money(fn (Cart $record): string => $record->currency, divideBy: 100)
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('instance')
                    ->options(
                        fn () => Cart::query()->forOwner()
                            ->select('instance')
                            ->distinct()
                            ->orderBy('instance')
                            ->pluck('instance', 'instance')
                            ->toArray()
                    )
                    ->multiple(),

                SelectFilter::make('currency')
                    ->options(
                        fn () => Cart::query()->forOwner()
                            ->select('currency')
                            ->distinct()
                            ->orderBy('currency')
                            ->pluck('currency', 'currency')
                            ->toArray()
                    ),

                Filter::make('has_items')
                    ->label('Has Items')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->notEmpty()),

                Filter::make('has_savings')
                    ->label('Has Savings')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->withSavings()),

                Filter::make('high_quantity')
                    ->label('10+ Units')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>=', 10)),

                Filter::make('recent')
                    ->label('Recent (7 days)')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->recent()),

                Filter::make('created_today')
                    ->label('Created Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
            ])
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                ActionGroup::make([
                    Action::make('clear_cart')
                        ->label('Clear Cart')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Cart $record): void {
                            /** @phpstan-ignore property.notFound */
                            app(CartInstanceManager::class)
                                ->resolve($record->instance, $record->identifier)
                                ->clear();
                        })
                        ->visible(fn (Cart $record): bool => /** @phpstan-ignore property.notFound */ $record->items_count > 0)
                        ->successNotificationTitle('Cart cleared'),

                    Action::make('view_items')
                        ->label('View Items')
                        ->icon(Heroicon::OutlinedListBullet)
                        ->url(fn (Cart $record) => CartResource::getUrl('view', ['record' => $record])),

                    DeleteAction::make()
                        ->icon(Heroicon::OutlinedXMark)
                        ->using(function (Cart $record): void {
                            app(CartInstanceManager::class)
                                ->resolve($record->instance, $record->identifier)
                                ->destroy();
                        })
                        ->successNotificationTitle('Cart deleted'),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->tooltip('More actions'),
            ])
            ->toolbarActions([
                BulkAction::make('clear_selected')
                    ->label('Clear Selected Carts')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var Collection<int|string, Cart> $records */
                        $records->each(function (Cart $record): void {
                            /** @phpstan-ignore property.notFound */
                            app(CartInstanceManager::class)
                                ->resolve($record->instance, $record->identifier)
                                ->clear();
                        });
                    }),

                BulkAction::make('delete_selected')
                    ->label('Delete Selected Carts')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records): void {
                        /** @var Collection<int|string, Cart> $records */
                        $records->each(function (Cart $record): void {
                            /** @phpstan-ignore property.notFound */
                            app(CartInstanceManager::class)
                                ->resolve($record->instance, $record->identifier)
                                ->destroy();
                        });
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
            ->poll(fn () => config('filament-cart.polling_interval', 30) . 's')
            ->striped();
    }
}
