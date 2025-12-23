<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Schemas;

use AIArmada\Jnt\Models\JntTrackingEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class JntTrackingEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event Summary')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('tracking_number')
                                ->label('Tracking Number')
                                ->icon(Heroicon::OutlinedTruck)
                                ->copyable()
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('order_reference')
                                ->label('Order Reference')
                                ->icon(Heroicon::OutlinedTag)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('scan_type_name')
                                ->label('Status')
                                ->badge()
                                ->color(fn (JntTrackingEvent $record): string => self::getStatusColor($record->scan_type_code)),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('scan_time')
                                ->label('Scan Time')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock),
                            TextEntry::make('scan_type_code')
                                ->label('Status Code')
                                ->badge()
                                ->color('secondary'),
                        ]),
                    TextEntry::make('description')
                        ->label('Description')
                        ->columnSpanFull()
                        ->placeholder('No description'),
                ]),

            Section::make('Location')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('scan_network_name')
                                ->label('Network Name')
                                ->icon(Heroicon::OutlinedBuildingOffice)
                                ->placeholder('—'),
                            TextEntry::make('scan_network_type_name')
                                ->label('Network Type')
                                ->placeholder('—'),
                        ]),
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('scan_network_area')
                                ->label('Area')
                                ->placeholder('—'),
                            TextEntry::make('scan_network_city')
                                ->label('City')
                                ->placeholder('—'),
                            TextEntry::make('scan_network_province')
                                ->label('Province')
                                ->placeholder('—'),
                            TextEntry::make('post_code')
                                ->label('Post Code')
                                ->placeholder('—'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('scan_network_contact')
                                ->label('Contact')
                                ->icon(Heroicon::OutlinedPhone)
                                ->placeholder('—'),
                            TextEntry::make('next_stop_name')
                                ->label('Next Stop')
                                ->icon(Heroicon::OutlinedArrowRight)
                                ->placeholder('—'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('latitude')
                                ->label('Latitude')
                                ->placeholder('—'),
                            TextEntry::make('longitude')
                                ->label('Longitude')
                                ->placeholder('—'),
                        ])
                        ->visible(fn (JntTrackingEvent $record): bool => filled($record->latitude) || filled($record->longitude)),
                ])
                ->collapsible(),

            Section::make('Staff & Delivery')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('staff_name')
                                ->label('Staff Name')
                                ->icon(Heroicon::OutlinedUserCircle)
                                ->placeholder('—'),
                            TextEntry::make('staff_contact')
                                ->label('Staff Contact')
                                ->icon(Heroicon::OutlinedPhone)
                                ->placeholder('—'),
                            TextEntry::make('otp')
                                ->label('OTP')
                                ->badge()
                                ->color('warning')
                                ->placeholder('—'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('actual_weight')
                                ->label('Actual Weight')
                                ->suffix(' kg')
                                ->placeholder('—'),
                            TextEntry::make('payment_status')
                                ->label('Payment Status')
                                ->badge()
                                ->placeholder('—'),
                        ]),
                ])
                ->collapsible()
                ->visible(fn (JntTrackingEvent $record): bool => filled($record->staff_name) || filled($record->actual_weight)),

            Section::make('Signature')
                ->schema([
                    TextEntry::make('signature_picture_url')
                        ->label('Signature Picture')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->placeholder('—'),
                    TextEntry::make('sign_url')
                        ->label('Sign URL')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->placeholder('—'),
                    TextEntry::make('electronic_signature_pic_url')
                        ->label('Electronic Signature')
                        ->url(fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->placeholder('—'),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (JntTrackingEvent $record): bool => filled($record->signature_picture_url) || filled($record->sign_url) || filled($record->electronic_signature_pic_url)),

            Section::make('Problem Details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('problem_type')
                                ->label('Problem Type')
                                ->badge()
                                ->color('danger'),
                            TextEntry::make('remark')
                                ->label('Remark')
                                ->placeholder('—'),
                        ]),
                ])
                ->collapsible()
                ->visible(fn (JntTrackingEvent $record): bool => filled($record->problem_type)),

            Section::make('Raw Payload')
                ->schema([
                    TextEntry::make('payload')
                        ->label('Payload JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                        ->visible(fn (JntTrackingEvent $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false) && filled($record->payload))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (JntTrackingEvent $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false) && filled($record->payload)),
        ]);
    }

    private static function getStatusColor(?string $statusCode): string
    {
        return match ($statusCode) {
            '100' => 'success',      // Delivered
            '10', '20', '30', '94' => 'info',  // In transit
            '110', '172', '173' => 'warning', // Problem/Return
            '200', '201', '300', '301', '302', '303', '304', '305', '306' => 'danger', // Terminal
            default => 'secondary',
        };
    }
}
