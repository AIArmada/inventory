<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Pages;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Processing;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

class FulfillmentQueue extends Page implements HasTable
{
    use InteractsWithTable;

    public bool $isTableVisible = true;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    protected string $view = 'filament-orders::pages.fulfillment-queue';

    protected static string | UnitEnum | null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Fulfillment Queue';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', Order::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function getNavigationBadge(): ?string
    {
        $includeGlobal = (bool) config('orders.owner.include_global', false);

        if ((bool) config('orders.owner.enabled', true) && OwnerContext::resolve() === null) {
            return null;
        }

        $owner = OwnerContext::resolve();
        $ownerKey = $owner ? ($owner->getMorphClass() . ':' . $owner->getKey()) : 'global';

        $cacheKey = sprintf('filament-orders.fulfillment-queue.badge.%s.%s', $ownerKey, $includeGlobal ? 'with-global' : 'owner-only');

        $count = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($includeGlobal): int {
            return Order::query()
                ->forOwner(includeGlobal: $includeGlobal)
                ->whereState('status', Processing::class)
                ->count();
        });

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $includeGlobal = (bool) config('orders.owner.include_global', false);

        if ((bool) config('orders.owner.enabled', true) && OwnerContext::resolve() === null) {
            return null;
        }

        $owner = OwnerContext::resolve();
        $ownerKey = $owner ? ($owner->getMorphClass() . ':' . $owner->getKey()) : 'global';

        $cacheKey = sprintf('filament-orders.fulfillment-queue.badge-color.%s.%s', $ownerKey, $includeGlobal ? 'with-global' : 'owner-only');

        $urgentCount = Cache::remember($cacheKey, now()->addSeconds(15), function () use ($includeGlobal): int {
            return Order::query()
                ->forOwner(includeGlobal: $includeGlobal)
                ->whereState('status', Processing::class)
                ->where('created_at', '<=', now()->subHours(48))
                ->count();
        });

        return $urgentCount > 0 ? 'danger' : 'success';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->forOwner(includeGlobal: (bool) config('orders.owner.include_global', false))
                    ->whereState('status', Processing::class)
                    ->with(['customer'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('#')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('Guest')
                    ->description(fn (Order $record): string => $record->customer?->email ?? 'Guest'),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->icon(fn ($state) => $state?->icon() ?? 'heroicon-o-question-mark-circle'),

                Tables\Columns\TextColumn::make('shipping_method')
                    ->label('Ship Via')
                    ->placeholder('Not set')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->formatStateUsing(
                        fn ($record) => $record->created_at->diffInHours(now()) > 48 ? 'High' : 'Normal'
                    )
                    ->color(
                        fn ($record) => $record->created_at->diffInHours(now()) > 48 ? 'danger' : 'success'
                    ),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                Tables\Filters\Filter::make('old_orders')
                    ->label('Older than 24h')
                    ->query(
                        fn (Builder $query): Builder => $query->where('created_at', '<=', now()->subHours(24))
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('urgent')
                    ->label('Urgent (>48h)')
                    ->query(
                        fn (Builder $query): Builder => $query->where('created_at', '<=', now()->subHours(48))
                    )
                    ->toggle(),

                Tables\Filters\SelectFilter::make('shipping_method')
                    ->label('Shipping Method')
                    ->options([
                        'standard' => 'Standard',
                        'express' => 'Express',
                        'overnight' => 'Overnight',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('fulfill')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->authorize(function (Order $record): bool {
                        $user = Filament::auth()->user();

                        return $user ? Gate::forUser($user)->allows('update', $record) : false;
                    })
                    ->form([
                        Forms\Components\Select::make('carrier')
                            ->label('Carrier')
                            ->options([
                                'poslaju' => 'Pos Laju',
                                'dhl' => 'DHL',
                                'fedex' => 'FedEx',
                                'jnt' => 'J&T Express',
                                'gdex' => 'GDex',
                            ])
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->maxLength(100),
                    ])
                    ->action(function (Order $record, array $data): void {
                        try {
                            $service = app(OrderService::class);
                            $service->ship(
                                $record,
                                $data['carrier'],
                                $data['tracking_number'],
                            );

                            Notification::make()
                                ->title('Order marked as shipped')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Unable to ship order')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Ship Order')
                    ->modalDescription(fn ($record) => "Complete shipment for order {$record->order_number}"),

                \Filament\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \AIArmada\FilamentOrders\Resources\OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->poll(fn (): ?string => $this->isTableVisible ? '30s' : null);
    }
}
