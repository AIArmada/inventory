<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $recordTitleAttribute = 'version_number';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->columns([
                TextColumn::make('version_number')
                    ->label('Version')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('change_summary')
                    ->label('Summary')
                    ->limit(50),

                TextColumn::make('changed_by')
                    ->label('Changed By'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->modalContent(function ($record) {
                        return view('filament-docs::partials.version-snapshot', [
                            'snapshot' => $record->snapshot,
                        ]);
                    }),
                Action::make('restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Version')
                    ->modalDescription('Are you sure you want to restore this version? Current data will be overwritten.')
                    ->action(function ($record): void {
                        $record->restore();
                        $this->dispatch('refresh');
                    }),
            ])
            ->defaultSort('version_number', 'desc');
    }
}
