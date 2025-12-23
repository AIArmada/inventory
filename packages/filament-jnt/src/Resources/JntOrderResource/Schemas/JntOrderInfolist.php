<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntOrderResource\Schemas;

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Services\JntStatusMapper;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class JntOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order Summary')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('order_id')
                                ->label('Order ID')
                                ->icon(Heroicon::OutlinedTag)
                                ->copyable()
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('tracking_number')
                                ->label('Tracking Number')
                                ->icon(Heroicon::OutlinedTruck)
                                ->copyable()
                                ->weight(FontWeight::SemiBold)
                                ->placeholder('—'),
                            TextEntry::make('last_status_code')
                                ->label('Status')
                                ->badge()
                                ->icon(fn (JntOrder $record): string => self::getNormalizedStatus($record)->icon())
                                ->color(fn (JntOrder $record): string => self::getNormalizedStatus($record)->color())
                                ->formatStateUsing(fn (JntOrder $record): string => self::getNormalizedStatus($record)->label()),
                        ]),
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('express_type')
                                ->label('Express Type')
                                ->badge()
                                ->color('info'),
                            TextEntry::make('service_type')
                                ->label('Service Type')
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    '1' => 'Door to Door',
                                    '6' => 'Walk-In',
                                    default => $state ?? '—',
                                }),
                            TextEntry::make('payment_type')
                                ->label('Payment Type')
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    'PP_PM' => 'Prepaid, Postpaid by Merchant',
                                    'PP_CASH' => 'Prepaid Cash',
                                    'CC_CASH' => 'Cash on Delivery',
                                    default => $state ?? '—',
                                }),
                            TextEntry::make('has_problem')
                                ->label('Has Problem')
                                ->badge()
                                ->color(fn (bool $state): string => $state ? 'danger' : 'success')
                                ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                        ]),
                ]),

            Section::make('Sender')
                ->schema([
                    Fieldset::make('Address Details')
                        ->schema([
                            TextEntry::make('sender.name')
                                ->label('Name')
                                ->icon(Heroicon::OutlinedUserCircle)
                                ->placeholder('—'),
                            TextEntry::make('sender.phone')
                                ->label('Phone')
                                ->icon(Heroicon::OutlinedPhone)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('sender.address')
                                ->label('Address')
                                ->columnSpanFull()
                                ->placeholder('—'),
                            TextEntry::make('sender.area')
                                ->label('Area')
                                ->placeholder('—'),
                            TextEntry::make('sender.city')
                                ->label('City')
                                ->placeholder('—'),
                            TextEntry::make('sender.prov')
                                ->label('State')
                                ->placeholder('—'),
                            TextEntry::make('sender.postCode')
                                ->label('Post Code')
                                ->placeholder('—'),
                        ])
                        ->columns(2),
                ])
                ->collapsible(),

            Section::make('Receiver')
                ->schema([
                    Fieldset::make('Address Details')
                        ->schema([
                            TextEntry::make('receiver.name')
                                ->label('Name')
                                ->icon(Heroicon::OutlinedUserCircle)
                                ->placeholder('—'),
                            TextEntry::make('receiver.phone')
                                ->label('Phone')
                                ->icon(Heroicon::OutlinedPhone)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('receiver.address')
                                ->label('Address')
                                ->columnSpanFull()
                                ->placeholder('—'),
                            TextEntry::make('receiver.area')
                                ->label('Area')
                                ->placeholder('—'),
                            TextEntry::make('receiver.city')
                                ->label('City')
                                ->placeholder('—'),
                            TextEntry::make('receiver.prov')
                                ->label('State')
                                ->placeholder('—'),
                            TextEntry::make('receiver.postCode')
                                ->label('Post Code')
                                ->placeholder('—'),
                        ])
                        ->columns(2),
                ])
                ->collapsible(),

            Section::make('Package Details')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('package_quantity')
                                ->label('Quantity')
                                ->icon(Heroicon::OutlinedCubeTransparent),
                            TextEntry::make('package_weight')
                                ->label('Weight')
                                ->suffix(' kg')
                                ->icon(Heroicon::OutlinedScale),
                            TextEntry::make('chargeable_weight')
                                ->label('Chargeable Weight')
                                ->suffix(' kg')
                                ->weight(FontWeight::SemiBold)
                                ->placeholder('—'),
                            TextEntry::make('goods_type')
                                ->label('Goods Type')
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    'ITN2' => 'Document',
                                    'ITN8' => 'Package',
                                    default => $state ?? '—',
                                }),
                        ]),
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('package_length')
                                ->label('Length')
                                ->suffix(' cm')
                                ->placeholder('—'),
                            TextEntry::make('package_width')
                                ->label('Width')
                                ->suffix(' cm')
                                ->placeholder('—'),
                            TextEntry::make('package_height')
                                ->label('Height')
                                ->suffix(' cm')
                                ->placeholder('—'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Financials')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('package_value')
                                ->label('Declared Value')
                                ->money('MYR')
                                ->placeholder('—'),
                            TextEntry::make('insurance_value')
                                ->label('Insurance')
                                ->money('MYR')
                                ->placeholder('—'),
                            TextEntry::make('cod_value')
                                ->label('Cash on Delivery')
                                ->money('MYR')
                                ->color('warning')
                                ->weight(FontWeight::SemiBold)
                                ->placeholder('—'),
                            TextEntry::make('offer_value')
                                ->label('Offer Value')
                                ->money('MYR')
                                ->placeholder('—'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Timeline')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('ordered_at')
                                ->label('Ordered')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock)
                                ->placeholder('—'),
                            TextEntry::make('pickup_start_at')
                                ->label('Pickup Start')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                            TextEntry::make('pickup_end_at')
                                ->label('Pickup End')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                            TextEntry::make('delivered_at')
                                ->label('Delivered')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedCheckCircle)
                                ->color('success')
                                ->placeholder('—'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('last_synced_at')
                                ->label('Last Synced')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                            TextEntry::make('last_tracked_at')
                                ->label('Last Tracked')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                        ]),
                ])
                ->collapsible(),

            Section::make('Tracking Events')
                ->schema([
                    RepeatableEntry::make('trackingEvents')
                        ->label('')
                        ->schema([
                            TextEntry::make('scan_time')
                                ->label('Time')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s')),
                            TextEntry::make('scan_type_code')
                                ->label('Status')
                                ->badge()
                                ->icon(fn (JntTrackingEvent $record): string => $record->getNormalizedStatus()->icon())
                                ->color(fn (JntTrackingEvent $record): string => $record->getNormalizedStatus()->color())
                                ->formatStateUsing(fn (JntTrackingEvent $record): string => $record->getNormalizedStatus()->label()),
                            TextEntry::make('description')
                                ->label('Description')
                                ->columnSpanFull()
                                ->placeholder('—'),
                            TextEntry::make('scan_network_name')
                                ->label('Location')
                                ->placeholder('—'),
                        ])
                        ->grid(1)
                        ->visible(fn (JntOrder $record): bool => $record->trackingEvents()->exists()),
                ])
                ->collapsible(),

            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight(FontWeight::Medium),
                            TextEntry::make('quantity')
                                ->label('Qty'),
                            TextEntry::make('weight_grams')
                                ->label('Weight')
                                ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 2) . ' kg'),
                            TextEntry::make('unit_price')
                                ->label('Unit Price')
                                ->money('MYR'),
                            TextEntry::make('description')
                                ->label('Description')
                                ->columnSpanFull()
                                ->placeholder('—'),
                        ])
                        ->grid(1)
                        ->visible(fn (JntOrder $record): bool => $record->items()->exists()),
                ])
                ->collapsible()
                ->collapsed(),

            Section::make('Notes')
                ->schema([
                    TextEntry::make('remark')
                        ->label('Remark')
                        ->columnSpanFull()
                        ->placeholder('No remarks'),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (JntOrder $record): bool => filled($record->remark)),

            Section::make('Raw Data')
                ->schema([
                    TextEntry::make('request_payload')
                        ->label('Request Payload')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                        ->visible(fn (JntOrder $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false) && filled($record->request_payload))
                        ->columnSpanFull(),
                    TextEntry::make('response_payload')
                        ->label('Response Payload')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                        ->visible(fn (JntOrder $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false) && filled($record->response_payload))
                        ->columnSpanFull(),
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                        ->visible(fn (JntOrder $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false) && filled($record->metadata))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (JntOrder $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false)
                    && (filled($record->request_payload) || filled($record->response_payload) || filled($record->metadata))),
        ]);
    }

    private static function getNormalizedStatus(JntOrder $order): TrackingStatus
    {
        if ($order->last_status_code === null) {
            return TrackingStatus::Pending;
        }

        return app(JntStatusMapper::class)->fromCode($order->last_status_code);
    }
}
