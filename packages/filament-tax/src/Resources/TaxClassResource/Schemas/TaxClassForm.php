<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxClassResource\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set as SetFormState;
use Filament\Schemas\Schema;

final class TaxClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Tax Class Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn (SetFormState $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state ?? ''))
                            ),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_default')
                            ->label('Default Tax Class')
                            ->helperText('Applied when no class is specified'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        TextInput::make('position')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }
}
