<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources;

use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Policies\SubscriptionPolicy;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\SubscriptionStatus;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class UnifiedSubscriptionResource extends Resource
{
    protected static ?string $model = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 10;

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.resources.navigation_sort.subscriptions', 10);
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::subscriptions.title');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cashier::subscriptions.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier::subscriptions.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function table(Table $table): Table
    {
        $gatewayDetector = app(GatewayDetector::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('userId')
                    ->label(__('filament-cashier::subscriptions.table.user'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('filament-cashier::subscriptions.table.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $gatewayDetector->getLabel($state))
                    ->color(fn (string $state): string => $gatewayDetector->getColor($state))
                    ->icon(fn (string $state): string => $gatewayDetector->getIcon($state)),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('filament-cashier::subscriptions.table.type'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('planId')
                    ->label(__('filament-cashier::subscriptions.table.plan'))
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('filament-cashier::subscriptions.table.status'))
                    ->badge()
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())
                    ->color(fn (SubscriptionStatus $state): string => $state->color())
                    ->icon(fn (SubscriptionStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('formattedAmount')
                    ->label(__('filament-cashier::subscriptions.table.amount'))
                    ->getStateUsing(fn (UnifiedSubscription $record): string => $record->formattedAmount()),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('filament-cashier::subscriptions.table.quantity'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('trialEndsAt')
                    ->label(__('filament-cashier::subscriptions.table.trial_ends_at'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('nextBillingDate')
                    ->label(__('filament-cashier::subscriptions.table.next_billing'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('createdAt')
                    ->label(__('filament-cashier::subscriptions.table.created_at'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label(__('filament-cashier::subscriptions.filters.gateway'))
                    ->options($gatewayDetector->getGatewayOptions())
                    ->query(fn (Builder $query, array $data): Builder => $query), // Handled in list page

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament-cashier::subscriptions.filters.status'))
                    ->options(
                        collect(SubscriptionStatus::cases())
                            ->mapWithKeys(fn (SubscriptionStatus $status) => [$status->value => $status->label()])
                            ->toArray()
                    )
                    ->query(fn (Builder $query, array $data): Builder => $query), // Handled in list page
            ])
            ->actions([
                ViewAction::make(),

                Action::make('cancel')
                    ->label(__('filament-cashier::subscriptions.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (UnifiedSubscription $record): bool => $record->status->isCancelable())
                    ->requiresConfirmation()
                    ->modalHeading(fn (UnifiedSubscription $record): string => __('filament-cashier::subscriptions.actions.cancel_heading', [
                        'gateway' => $record->gatewayConfig()['label'],
                    ]))
                    ->modalDescription(__('filament-cashier::subscriptions.actions.cancel_description'))
                    ->action(function (UnifiedSubscription $record): void {
                        $user = auth()->user();

                        if ($user === null || ! app(SubscriptionPolicy::class)->cancel($user, $record->original)) {
                            throw new AuthorizationException('Not authorized to cancel this subscription.');
                        }

                        if (method_exists($record->original, 'cancel')) {
                            $record->original->cancel();
                        }
                    })
                    ->successNotificationTitle(__('filament-cashier::subscriptions.actions.cancel_success')),

                Action::make('resume')
                    ->label(__('filament-cashier::subscriptions.actions.resume'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (UnifiedSubscription $record): bool => $record->status->isResumable())
                    ->requiresConfirmation()
                    ->action(function (UnifiedSubscription $record): void {
                        $user = auth()->user();

                        if ($user === null || ! app(SubscriptionPolicy::class)->resume($user, $record->original)) {
                            throw new AuthorizationException('Not authorized to resume this subscription.');
                        }

                        if (method_exists($record->original, 'resume')) {
                            $record->original->resume();
                        }
                    })
                    ->successNotificationTitle(__('filament-cashier::subscriptions.actions.resume_success')),

                Action::make('view_external')
                    ->label(fn (UnifiedSubscription $record): string => __('filament-cashier::subscriptions.actions.view_external', [
                        'gateway' => $record->gatewayConfig()['label'],
                    ]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (UnifiedSubscription $record): string => $record->externalDashboardUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('cancel')
                        ->label(__('filament-cashier::subscriptions.bulk.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $user = auth()->user();

                            if ($user === null) {
                                throw new AuthorizationException('Not authorized to cancel subscriptions.');
                            }

                            $policy = app(SubscriptionPolicy::class);

                            $records->each(function (UnifiedSubscription $record) use ($user, $policy): void {
                                if (! $policy->cancel($user, $record->original)) {
                                    throw new AuthorizationException('Not authorized to cancel this subscription.');
                                }

                                if (method_exists($record->original, 'cancel')) {
                                    $record->original->cancel();
                                }
                            });
                        }),
                ]),
            ])
            ->defaultSort('createdAt', 'desc')
            ->poll(config('filament-cashier.tables.polling_interval', '45s'))
            ->emptyStateHeading(__('filament-cashier::subscriptions.empty.title'))
            ->emptyStateDescription(__('filament-cashier::subscriptions.empty.description'))
            ->emptyStateIcon('heroicon-o-credit-card');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
            'create' => Pages\CreateSubscription::route('/create'),
        ];
    }

    /**
     * Disable Eloquent binding - we use DTOs.
     */
    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        return null;
    }
}
