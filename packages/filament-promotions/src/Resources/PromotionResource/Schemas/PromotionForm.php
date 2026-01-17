<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas;

use AIArmada\FilamentPromotions\Enums\PromotionType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->description('Configure the promotion details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('code')
                            ->label('Promo Code')
                            ->helperText('Leave empty for automatic promotions')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),

                Section::make('Discount Configuration')
                    ->description('Define the discount type and value')
                    ->schema([
                        Select::make('type')
                            ->options(PromotionType::class)
                            ->required()
                            ->native(false),

                        TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->required()
                            ->numeric()
                            ->helperText('For percentage: enter number (e.g., 20 for 20%). For fixed: enter cents (e.g., 1000 for $10)'),

                        TextInput::make('min_order_value')
                            ->label('Minimum Order Value')
                            ->numeric()
                            ->helperText('In cents (e.g., 5000 for $50)')
                            ->nullable(),

                        TextInput::make('max_discount')
                            ->label('Maximum Discount')
                            ->numeric()
                            ->helperText('Cap for percentage discounts, in cents')
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Usage Limits')
                    ->description('Control how many times the promotion can be used')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('usage_limit')
                                    ->label('Total Usage Limit')
                                    ->numeric()
                                    ->helperText('Leave empty for unlimited')
                                    ->nullable(),

                                TextInput::make('usage_per_customer')
                                    ->label('Per Customer Limit')
                                    ->numeric()
                                    ->helperText('Leave empty for unlimited')
                                    ->nullable(),
                            ]),
                    ]),

                Section::make('Scheduling')
                    ->description('Set when the promotion is active')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Start Date')
                                    ->helperText('Leave empty to start immediately'),

                                DateTimePicker::make('ends_at')
                                    ->label('End Date')
                                    ->helperText('Leave empty for no end date'),
                            ]),
                    ]),

                Section::make('Targeting Conditions')
                    ->description('Define conditions for when this promotion applies')
                    ->schema([
                        KeyValue::make('conditions')
                            ->keyLabel('Condition')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Options')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Toggle::make('is_stackable')
                                    ->label('Stackable')
                                    ->helperText('Can combine with other promotions')
                                    ->default(false),

                                TextInput::make('priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher values take precedence'),
                            ]),
                    ]),
            ]);
    }
}
