<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'options';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Option Name')
                    ->required()
                    ->placeholder('e.g., Size, Color, Material')
                    ->maxLength(100),

                Forms\Components\TextInput::make('display_name')
                    ->label('Display Name')
                    ->placeholder('e.g., Select your size')
                    ->maxLength(255),

                Forms\Components\TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Forms\Components\Toggle::make('is_visible')
                    ->label('Visible to customers')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('position')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Option Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name')
                    ->placeholder('Not set'),

                Tables\Columns\TextColumn::make('values_count')
                    ->label('Values')
                    ->counts('values')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_values')
                    ->label('Values')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Manage Values for {$record->name}")
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Repeater::make('option_values')
                            ->label('Option Values')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Value')
                                    ->required()
                                    ->placeholder('e.g., Small, Red'),

                                Forms\Components\ColorPicker::make('swatch_color')
                                    ->label('Swatch Color'),

                                Forms\Components\TextInput::make('position')
                                    ->label('Position')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->defaultItems(0)
                            ->addActionLabel('Add Value'),
                    ])
                    ->fillForm(fn ($record) => [
                        'option_values' => $record->values->map(fn ($v) => [
                            'id' => $v->id,
                            'name' => $v->name,
                            'swatch_color' => $v->swatch_color,
                            'position' => $v->position,
                        ])->toArray(),
                    ])
                    ->action(function ($record, array $data): void {
                        // Get existing value IDs
                        $existingIds = $record->values->pluck('id')->toArray();
                        $newIds = [];

                        foreach ($data['option_values'] ?? [] as $index => $valueData) {
                            if (isset($valueData['id']) && in_array($valueData['id'], $existingIds)) {
                                // Update existing
                                $record->values()
                                    ->where('id', $valueData['id'])
                                    ->update([
                                        'name' => $valueData['name'],
                                        'swatch_color' => $valueData['swatch_color'] ?? null,
                                        'position' => $valueData['position'] ?? $index,
                                    ]);
                                $newIds[] = $valueData['id'];
                            } else {
                                // Create new
                                $newValue = $record->values()->create([
                                    'name' => $valueData['name'],
                                    'swatch_color' => $valueData['swatch_color'] ?? null,
                                    'position' => $valueData['position'] ?? $index,
                                ]);
                                $newIds[] = $newValue->id;
                            }
                        }

                        // Delete removed values
                        $toDelete = array_diff($existingIds, $newIds);
                        if (! empty($toDelete)) {
                            $record->values()->whereIn('id', $toDelete)->delete();
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Option values updated')
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
