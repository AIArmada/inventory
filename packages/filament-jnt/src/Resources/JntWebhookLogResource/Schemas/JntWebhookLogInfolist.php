<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Schemas;

use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\Jnt\Models\JntWebhookLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class JntWebhookLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Webhook Summary')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('tracking_number')
                                ->label('Tracking Number')
                                ->icon(Heroicon::OutlinedTruck)
                                ->copyable()
                                ->weight(FontWeight::SemiBold)
                                ->placeholder('—'),
                            TextEntry::make('order_reference')
                                ->label('Order Reference')
                                ->icon(Heroicon::OutlinedTag)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('processing_status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'processed' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'danger',
                                    default => 'secondary',
                                }),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Received')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock),
                            TextEntry::make('processed_at')
                                ->label('Processed')
                                ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedCheckCircle)
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Error Details')
                ->schema([
                    TextEntry::make('processing_error')
                        ->label('Error Message')
                        ->columnSpanFull()
                        ->color('danger'),
                ])
                ->collapsible()
                ->visible(fn (JntWebhookLog $record): bool => filled($record->processing_error)),

            Section::make('Request Details')
                ->schema([
                    TextEntry::make('digest')
                        ->label('Digest')
                        ->copyable()
                        ->placeholder('—'),
                    TextEntry::make('headers')
                        ->label('Headers')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                        ->visible(fn (JntWebhookLog $record): bool => filled($record->headers))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (JntWebhookLog $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false)
                    && (filled($record->digest) || filled($record->headers))),

            Section::make('Payload')
                ->schema([
                    TextEntry::make('payload')
                        ->label('Payload JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '')
                        ->visible(fn (JntWebhookLog $record): bool => filled($record->payload))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (JntWebhookLog $record): bool => (bool) config('filament-jnt.features.show_raw_payloads', false) && filled($record->payload)),

            Section::make('Related Order')
                ->schema([
                    TextEntry::make('order.order_id')
                        ->label('Order ID')
                        ->icon(Heroicon::OutlinedShoppingBag)
                        ->url(fn (JntWebhookLog $record): ?string => $record->order_id
                            ? JntOrderResource::getUrl('view', ['record' => $record->order_id])
                            : null)
                        ->placeholder('Not linked'),
                ])
                ->collapsible()
                ->visible(fn (JntWebhookLog $record): bool => filled($record->order_id)),
        ]);
    }
}
