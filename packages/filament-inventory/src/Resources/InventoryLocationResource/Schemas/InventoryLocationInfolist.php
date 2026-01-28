<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class InventoryLocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Location Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')
                            ->label('Name')
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('code')
                            ->label('Code')
                            ->badge()
                            ->color('info')
                            ->copyable(),

                        TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                    ]),

                    TextEntry::make('line1')
                        ->label('Address Line 1')
                        ->placeholder('—'),

                    TextEntry::make('line2')
                        ->label('Address Line 2')
                        ->placeholder('—'),

                    Grid::make(3)->schema([
                        TextEntry::make('city')
                            ->label('City')
                            ->placeholder('—'),

                        TextEntry::make('state')
                            ->label('State')
                            ->placeholder('—'),

                        TextEntry::make('postcode')
                            ->label('Postcode')
                            ->placeholder('—'),
                    ]),

                    TextEntry::make('country')
                        ->label('Country')
                        ->placeholder('—'),

                    Grid::make(3)->schema([
                        TextEntry::make('priority')
                            ->label('Priority')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ]),
                ]),

            Section::make('Statistics')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('inventoryLevels')
                            ->label('SKUs')
                            ->formatStateUsing(fn ($record): string => (string) $record->inventoryLevels()->count()),

                        TextEntry::make('total_on_hand')
                            ->label('Total On Hand')
                            ->formatStateUsing(fn ($record): string => number_format($record->inventoryLevels()->sum('quantity_on_hand'))),

                        TextEntry::make('total_reserved')
                            ->label('Total Reserved')
                            ->formatStateUsing(fn ($record): string => number_format($record->inventoryLevels()->sum('quantity_reserved'))),
                    ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT) : '—')
                        ->prose()
                        ->markdown(),
                ])
                ->collapsed(),
        ]);
    }
}
