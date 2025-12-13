<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;

use AIArmada\Customers\Enums\AddressType;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $recordTitleAttribute = 'label';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->placeholder('e.g., Home, Office')
                    ->maxLength(100),

                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(
                        collect(AddressType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    )
                    ->required()
                    ->default('both'),

                Forms\Components\TextInput::make('recipient_name')
                    ->label('Recipient Name')
                    ->maxLength(255),

                Forms\Components\TextInput::make('company')
                    ->label('Company')
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(20),

                Forms\Components\TextInput::make('address_line_1')
                    ->label('Address Line 1')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('address_line_2')
                    ->label('Address Line 2')
                    ->maxLength(255),

                Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('State')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('postcode')
                            ->label('Postcode')
                            ->required()
                            ->maxLength(20),
                    ]),

                Forms\Components\Select::make('country')
                    ->label('Country')
                    ->options([
                        'MY' => 'Malaysia',
                        'SG' => 'Singapore',
                        'ID' => 'Indonesia',
                        'TH' => 'Thailand',
                        'BN' => 'Brunei',
                    ])
                    ->default('MY')
                    ->required(),

                Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_default_billing')
                            ->label('Default Billing'),
                        Forms\Components\Toggle::make('is_default_shipping')
                            ->label('Default Shipping'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('Unnamed'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),

                Tables\Columns\TextColumn::make('full_address')
                    ->label('Address')
                    ->limit(50)
                    ->searchable(['address_line_1', 'city', 'postcode']),

                Tables\Columns\IconColumn::make('is_default_billing')
                    ->label('Billing')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default_shipping')
                    ->label('Shipping')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(
                        collect(AddressType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('set_billing')
                    ->label('Set as Billing')
                    ->icon('heroicon-o-credit-card')
                    ->action(fn ($record) => $record->setAsDefaultBilling())
                    ->visible(fn ($record) => ! $record->is_default_billing),
                Tables\Actions\Action::make('set_shipping')
                    ->label('Set as Shipping')
                    ->icon('heroicon-o-truck')
                    ->action(fn ($record) => $record->setAsDefaultShipping())
                    ->visible(fn ($record) => ! $record->is_default_shipping),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
