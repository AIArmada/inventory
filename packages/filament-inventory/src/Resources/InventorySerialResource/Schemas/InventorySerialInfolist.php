<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Schemas;

use AIArmada\Inventory\Models\InventorySerial;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class InventorySerialInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Serial Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('serial_number')
                            ->label('Serial Number')
                            ->weight(FontWeight::SemiBold)
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (InventorySerial $record): string => $record->getStatusEnum()->color()),

                        TextEntry::make('condition')
                            ->label('Condition')
                            ->badge()
                            ->color(fn (InventorySerial $record): string => $record->getConditionEnum()->color()),
                    ]),

                    Grid::make(2)->schema([
                        TextEntry::make('location.name')
                            ->label('Location')
                            ->weight(FontWeight::SemiBold)
                            ->placeholder('No location'),

                        TextEntry::make('batch.batch_number')
                            ->label('Batch')
                            ->placeholder('No batch'),
                    ]),
                ]),

            Section::make('Product Information')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('inventoryable_type')
                            ->label('Product Type')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),

                        TextEntry::make('inventoryable_id')
                            ->label('Product ID')
                            ->copyable(),
                    ]),
                ]),

            Section::make('Cost & Warranty')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('unit_cost_minor')
                            ->label('Unit Cost (Minor)')
                            ->numeric()
                            ->placeholder('—'),

                        TextEntry::make('warranty_expires_at')
                            ->label('Warranty Expires')
                            ->date()
                            ->color(fn (InventorySerial $record): string => match (true) {
                                $record->warranty_expires_at === null => 'gray',
                                $record->warranty_expires_at->isPast() => 'danger',
                                $record->warranty_expires_at->diffInDays(now()) <= 30 => 'warning',
                                default => 'success',
                            })
                            ->placeholder('No warranty'),

                        TextEntry::make('warranty_status')
                            ->label('Warranty Status')
                            ->state(fn (InventorySerial $record): string => match (true) {
                                $record->warranty_expires_at === null => '—',
                                $record->warranty_expires_at->isPast() => 'Expired',
                                default => 'Active (' . $record->warranty_expires_at->diffForHumans() . ')',
                            })
                            ->badge()
                            ->color(fn (InventorySerial $record): string => match (true) {
                                $record->warranty_expires_at === null => 'gray',
                                $record->warranty_expires_at->isPast() => 'danger',
                                default => 'success',
                            }),
                    ]),
                ]),

            Section::make('Order Information')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('order_id')
                            ->label('Order ID')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('customer_id')
                            ->label('Customer ID')
                            ->copyable()
                            ->placeholder('—'),
                    ]),
                ])
                ->hidden(fn (InventorySerial $record): bool => $record->order_id === null && $record->customer_id === null),

            Section::make('Dates')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('received_at')
                            ->label('Received')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('sold_at')
                            ->label('Sold')
                            ->date()
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('Timestamps')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ]),
                ])
                ->collapsed(),
        ]);
    }
}
