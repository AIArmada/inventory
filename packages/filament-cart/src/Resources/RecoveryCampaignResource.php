<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources;

use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryTemplate;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages\CreateRecoveryCampaign;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages\EditRecoveryCampaign;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages\ListRecoveryCampaigns;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource\Pages\ViewRecoveryCampaign;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class RecoveryCampaignResource extends Resource
{
    protected static ?string $model = RecoveryCampaign::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Recovery Campaigns';

    protected static ?string $modelLabel = 'Recovery Campaign';

    protected static ?string $pluralModelLabel = 'Recovery Campaigns';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(2),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'completed' => 'Completed',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Trigger Configuration')
                    ->schema([
                        Select::make('trigger_type')
                            ->options([
                                'abandoned' => 'Cart Abandoned',
                                'high_value' => 'High Value Cart',
                                'exit_intent' => 'Exit Intent',
                                'custom' => 'Custom Trigger',
                            ])
                            ->required(),
                        TextInput::make('trigger_delay_minutes')
                            ->label('Trigger Delay (minutes)')
                            ->numeric()
                            ->default(60)
                            ->required(),
                        TextInput::make('max_attempts')
                            ->label('Maximum Attempts')
                            ->numeric()
                            ->default(3)
                            ->required(),
                        TextInput::make('attempt_interval_hours')
                            ->label('Hours Between Attempts')
                            ->numeric()
                            ->default(24)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Targeting')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_cart_value_cents')
                                    ->label('Minimum Cart Value (cents)')
                                    ->numeric()
                                    ->placeholder('e.g. 1000 for $10'),
                                TextInput::make('max_cart_value_cents')
                                    ->label('Maximum Cart Value (cents)')
                                    ->numeric()
                                    ->placeholder('e.g. 100000 for $1000'),
                                TextInput::make('min_items')
                                    ->label('Minimum Items')
                                    ->numeric(),
                                TextInput::make('max_items')
                                    ->label('Maximum Items')
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('Strategy')
                    ->schema([
                        Select::make('strategy')
                            ->options([
                                'email' => 'Email Only',
                                'sms' => 'SMS Only',
                                'push' => 'Push Notification',
                                'multi_channel' => 'Multi-Channel',
                            ])
                            ->default('email')
                            ->required(),
                        Checkbox::make('offer_discount')
                            ->label('Offer Discount')
                            ->reactive(),
                        Select::make('discount_type')
                            ->options([
                                'percentage' => 'Percentage Off',
                                'fixed' => 'Fixed Amount Off',
                            ])
                            ->visible(fn ($get) => $get('offer_discount')),
                        TextInput::make('discount_value')
                            ->numeric()
                            ->visible(fn ($get) => $get('offer_discount')),
                        Checkbox::make('offer_free_shipping')
                            ->label('Offer Free Shipping'),
                        TextInput::make('urgency_hours')
                            ->label('Offer Expires In (hours)')
                            ->numeric()
                            ->helperText('Leave empty for no expiry'),
                    ])
                    ->columns(2),

                Section::make('A/B Testing')
                    ->schema([
                        Checkbox::make('ab_testing_enabled')
                            ->label('Enable A/B Testing')
                            ->reactive(),
                        TextInput::make('ab_test_split_percent')
                            ->label('Variant Split (%)')
                            ->numeric()
                            ->default(50)
                            ->visible(fn ($get) => $get('ab_testing_enabled')),
                        Select::make('control_template_id')
                            ->label('Control Template')
                            ->options(fn () => RecoveryTemplate::query()->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn ($get) => $get('ab_testing_enabled')),
                        Select::make('variant_template_id')
                            ->label('Variant Template')
                            ->options(fn () => RecoveryTemplate::query()->pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn ($get) => $get('ab_testing_enabled')),
                    ])
                    ->columns(2),

                Section::make('Schedule')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Start Date'),
                        DateTimePicker::make('ends_at')
                            ->label('End Date'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'draft' => 'gray',
                        'completed' => 'info',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('strategy')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('total_sent')
                    ->label('Sent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_recovered')
                    ->label('Recovered')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conversion_rate')
                    ->label('Conv. Rate')
                    ->getStateUsing(fn (RecoveryCampaign $record) => number_format($record->getConversionRate() * 100, 1) . '%')
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw('total_recovered / NULLIF(total_sent, 0) ' . $direction)),
                TextColumn::make('recovered_revenue_cents')
                    ->label('Revenue')
                    ->money('USD', divideBy: 100)
                    ->sortable(),
                IconColumn::make('ab_testing_enabled')
                    ->label('A/B')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('strategy')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                        'multi_channel' => 'Multi-Channel',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecoveryCampaigns::route('/'),
            'create' => CreateRecoveryCampaign::route('/create'),
            'view' => ViewRecoveryCampaign::route('/{record}'),
            'edit' => EditRecoveryCampaign::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-cart.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-cart.resources.navigation_sort.recovery_campaigns', 40);
    }
}
