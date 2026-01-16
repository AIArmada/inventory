<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Schemas;

use AIArmada\Inventory\Models\InventoryBatch;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class InventoryBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('batch_number')
                            ->label('Batch Number')
                            ->weight(FontWeight::SemiBold)
                            ->copyable(),

                        TextEntry::make('lot_number')
                            ->label('Lot Number')
                            ->placeholder('—')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (InventoryBatch $record): string => $record->getStatusEnum()->color()),
                    ]),

                    Grid::make(2)->schema([
                        TextEntry::make('location.name')
                            ->label('Location')
                            ->weight(FontWeight::SemiBold)
                            ->placeholder('No location'),

                        TextEntry::make('supplier_batch_number')
                            ->label('Supplier Batch')
                            ->placeholder('—'),
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

            Section::make('Quantities')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('quantity_received')
                            ->label('Received')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('gray'),

                        TextEntry::make('quantity_on_hand')
                            ->label('On Hand')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('info'),

                        TextEntry::make('quantity_reserved')
                            ->label('Reserved')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('warning'),

                        TextEntry::make('available')
                            ->label('Available')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color(fn (InventoryBatch $record): string => $record->available_quantity <= 0 ? 'danger' : 'success')
                            ->state(fn (InventoryBatch $record): int => $record->available_quantity),
                    ]),
                ]),

            Section::make('Dates')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('manufactured_at')
                            ->label('Manufactured')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('received_at')
                            ->label('Received')
                            ->date()
                            ->placeholder('—'),

                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->date()
                            ->color(fn (InventoryBatch $record): string => match (true) {
                                $record->is_expired => 'danger',
                                ($record->days_until_expiry ?? 999) <= 7 => 'warning',
                                default => 'success',
                            })
                            ->placeholder('No expiry'),

                        TextEntry::make('days_until_expiry')
                            ->label('Days Left')
                            ->state(fn (InventoryBatch $record): string => match (true) {
                                $record->expires_at === null => '—',
                                $record->is_expired => 'Expired',
                                default => $record->days_until_expiry . ' days',
                            })
                            ->badge()
                            ->color(fn (InventoryBatch $record): string => match (true) {
                                $record->expires_at === null => 'gray',
                                $record->is_expired => 'danger',
                                ($record->days_until_expiry ?? 999) <= 7 => 'warning',
                                default => 'success',
                            }),
                    ]),
                ]),

            Section::make('Cost')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('unit_cost_minor')
                            ->label('Unit Cost (Minor)')
                            ->numeric()
                            ->placeholder('—'),

                        TextEntry::make('total_value')
                            ->label('Total Value (Minor)')
                            ->state(
                                fn (InventoryBatch $record): ?int => $record->unit_cost_minor !== null
                                ? $record->unit_cost_minor * $record->quantity_on_hand
                                : null
                            )
                            ->numeric()
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('Notes')
                ->schema([
                    TextEntry::make('notes')
                        ->label('')
                        ->prose()
                        ->placeholder('No notes'),
                ])
                ->collapsed()
                ->hidden(fn (InventoryBatch $record): bool => empty($record->notes)),

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
