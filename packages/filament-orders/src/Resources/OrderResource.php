<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources;

use AIArmada\FilamentOrders\Resources\OrderResource\Pages;
use AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use BackedEnum;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | UnitEnum | null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getEloquentQuery(): Builder
    {
        $includeGlobal = (bool) config('orders.owner.include_global', false);

        return Order::query()
            ->forOwner(includeGlobal: $includeGlobal)
            ->with(['customer']);
    }

    public static function getNavigationBadge(): ?string
    {
        return self::getEloquentQuery()->whereState('status', [PendingPayment::class, Processing::class])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->label('Order Number')
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending_payment' => 'Pending Payment',
                                'processing' => 'Processing',
                                'on_hold' => 'On Hold',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'completed' => 'Completed',
                                'canceled' => 'Canceled',
                                'returned' => 'Returned',
                                'refunded' => 'Refunded',
                            ])
                            ->disabled()
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('Customer Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Totals')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('discount_total')
                            ->label('Discount')
                            ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('shipping_total')
                            ->label('Shipping')
                            ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('tax_total')
                            ->label('Tax')
                            ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('grand_total')
                            ->label('Grand Total')
                            ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                            ->numeric()
                            ->disabled(),
                    ])
                    ->columns(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
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
                    })
                    ->icon(fn ($state) => $state?->icon() ?? 'heroicon-o-question-mark-circle'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Guest')
                    ->searchable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Not paid')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_payment' => 'Pending Payment',
                        'processing' => 'Processing',
                        'on_hold' => 'On Hold',
                        'fraud' => 'Fraud',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed',
                        'canceled' => 'Canceled',
                        'returned' => 'Returned',
                        'refunded' => 'Refunded',
                        'payment_failed' => 'Payment Failed',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('paid')
                    ->label('Paid Orders')
                    ->query(fn (Builder $query) => $query->whereNotNull('paid_at')),

                Tables\Filters\Filter::make('unpaid')
                    ->label('Unpaid Orders')
                    ->query(fn (Builder $query) => $query->whereNull('paid_at')),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('download_invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Order $record) => route('filament-orders.invoice.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Order $record) => $record->isPaid()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Order Details')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Order Number')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('status')
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

                        TextEntry::make('created_at')
                            ->label('Order Date')
                            ->dateTime('d M Y H:i'),

                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Not paid'),
                    ])
                    ->columns(4),

                Section::make('Customer')
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Name')
                            ->placeholder('Guest'),

                        TextEntry::make('customer.email')
                            ->label('Email')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                Section::make('Addresses')
                    ->schema([
                        TextEntry::make('billingAddress.formatted')
                            ->label('Billing Address')
                            ->getStateUsing(function (Order $record): ?HtmlString {
                                if (! $record->billingAddress) {
                                    return null;
                                }

                                return new HtmlString(nl2br(e($record->billingAddress->getFormatted())));
                            })
                            ->placeholder('Not provided')
                            ->html(),

                        TextEntry::make('shippingAddress.formatted')
                            ->label('Shipping Address')
                            ->getStateUsing(function (Order $record): ?HtmlString {
                                if (! $record->shippingAddress) {
                                    return null;
                                }

                                return new HtmlString(nl2br(e($record->shippingAddress->getFormatted())));
                            })
                            ->placeholder('Not provided')
                            ->html(),
                    ])
                    ->columns(2),

                Section::make('Order Totals')
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money(fn (Order $record): string => $record->currency, divideBy: 100),

                        TextEntry::make('discount_total')
                            ->label('Discount')
                            ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                            ->visible(fn ($record) => $record->discount_total > 0),

                        TextEntry::make('shipping_total')
                            ->label('Shipping')
                            ->money(fn (Order $record): string => $record->currency, divideBy: 100),

                        TextEntry::make('tax_total')
                            ->label('Tax')
                            ->money(fn (Order $record): string => $record->currency, divideBy: 100),

                        TextEntry::make('grand_total')
                            ->label('Grand Total')
                            ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                            ->weight('bold')
                            ->size('lg'),
                    ])
                    ->columns(5),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Customer Notes')
                            ->placeholder('No notes'),

                        TextEntry::make('internal_notes')
                            ->label('Internal Notes')
                            ->placeholder('No internal notes'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number'];
    }
}
