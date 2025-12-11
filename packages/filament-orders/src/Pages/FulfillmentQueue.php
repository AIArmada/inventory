<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Pages;

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Shipped;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FulfillmentQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static string $view = 'filament-orders::pages.fulfillment-queue';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Fulfillment Queue';

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()
            ->whereIn('status', [Processing::class])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $urgentCount = Order::query()
            ->whereIn('status', [Processing::class])
            ->where('created_at', '<=', now()->subHours(48))
            ->count();

        return $urgentCount > 0 ? 'danger' : 'success';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->whereIn('status', [Processing::class])
                    ->with(['customer', 'items'])
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
                    ->description(fn ($record) => $record->customer->email),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('MYR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->icon(fn ($state) => $state->icon()),

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
                Tables\Actions\Action::make('fulfill')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('success')
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

                        Forms\Components\DateTimePicker::make('shipped_at')
                            ->label('Ship Date')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Shipping Notes')
                            ->rows(2),
                    ])
                    ->action(function (Order $record, array $data): void {
                        // Update order with shipping info
                        $record->update([
                            'shipping_carrier' => $data['carrier'],
                            'tracking_number' => $data['tracking_number'],
                            'shipped_at' => $data['shipped_at'],
                        ]);

                        // Transition to shipped status
                        $record->status->transitionTo(Shipped::class);

                        // Optional: Send notification to customer
                        // event(new OrderShipped($record));
                    })
                    ->successNotificationTitle('Order marked as shipped')
                    ->modalHeading('Ship Order')
                    ->modalDescription(fn ($record) => "Complete shipment for order {$record->order_number}"),

                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \AIArmada\FilamentOrders\Resources\OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('print_packing_slip')
                    ->label('Packing Slip')
                    ->icon('heroicon-o-printer')
                    ->url(fn ($record) => route('orders.packing-slip', $record))
                    ->openUrlInNewTab()
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_ship')
                        ->label('Bulk Ship')
                        ->icon('heroicon-o-truck')
                        ->color('success')
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
                                ->required(),

                            Forms\Components\Repeater::make('tracking_numbers')
                                ->label('Tracking Numbers')
                                ->schema([
                                    Forms\Components\Placeholder::make('order')
                                        ->label('Order')
                                        ->content(fn ($state, $record) => $record?->order_number ?? 'Loading...'),
                                    Forms\Components\TextInput::make('tracking')
                                        ->label('Tracking #')
                                        ->required(),
                                ])
                                ->columns(2)
                                ->default(function ($records) {
                                    return $records->map(fn ($record) => [
                                        'order' => $record->order_number,
                                        'tracking' => '',
                                    ])->toArray();
                                }),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $index => $record) {
                                if (isset($data['tracking_numbers'][$index]['tracking'])) {
                                    $record->update([
                                        'shipping_carrier' => $data['carrier'],
                                        'tracking_number' => $data['tracking_numbers'][$index]['tracking'],
                                        'shipped_at' => now(),
                                    ]);

                                    $record->status->transitionTo(Shipped::class);
                                }
                            }
                        })
                        ->successNotificationTitle('Orders marked as shipped')
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('print_packing_slips')
                        ->label('Print Packing Slips')
                        ->icon('heroicon-o-printer')
                        ->url(fn ($records) => route('orders.bulk-packing-slips', [
                            'orders' => $records->pluck('id')->toArray(),
                        ]))
                        ->openUrlInNewTab()
                        ->color('gray'),
                ]),
            ])
            ->poll('30s');
    }
}
