<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Schemas;

use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as GetFormState;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class TaxRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Rate Details')
                            ->schema([
                                Select::make('zone_id')
                                    ->label('Tax Zone')
                                    ->relationship(
                                        'zone',
                                        'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => TaxOwnerScope::applyToOwnedQuery($query),
                                    )
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('code')->required(),
                                    ]),

                                TextInput::make('name')
                                    ->label('Rate Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., SST, VAT, GST')
                                    ->helperText('Descriptive name for this tax rate'),

                                Select::make('tax_class')
                                    ->label('Tax Class')
                                    ->options([
                                        'standard' => 'Standard',
                                        'reduced' => 'Reduced',
                                        'zero' => 'Zero Rate',
                                        'exempt' => 'Exempt',
                                    ])
                                    ->default('standard')
                                    ->required(),

                                TextInput::make('rate')
                                    ->label('Tax Rate')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->formatStateUsing(fn ($state): ?float => $state === null ? null : ((int) $state) / 100)
                                    ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100))
                                    ->helperText('Tax percentage (e.g., 6 for 6%)'),
                            ])
                            ->columns(2),

                        Section::make('Application Rules')
                            ->schema([
                                Toggle::make('is_compound')
                                    ->label('Compound Tax')
                                    ->helperText('Calculate this tax on top of other taxes')
                                    ->default(false)
                                    ->inline(false),

                                Toggle::make('is_shipping')
                                    ->label('Apply to Shipping')
                                    ->helperText('Include shipping costs in tax calculation')
                                    ->default(true)
                                    ->inline(false),

                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher priority rates are applied first')
                                    ->minValue(0),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Enable/disable this tax rate'),

                                Placeholder::make('rate_info')
                                    ->label('Effective Rate')
                                    ->content(function ($record, GetFormState $get) {
                                        $rate = $get('rate') ?? $record?->rate ?? 0;

                                        return number_format(((float) $rate) / 100, 2) . '%';
                                    }),

                                Placeholder::make('created_info')
                                    ->label('Created')
                                    ->content(fn ($record) => $record?->created_at?->diffForHumans() ?? 'Not created yet'),
                            ]),

                        Section::make('Additional Info')
                            ->schema([
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->helperText('Optional description or notes'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
