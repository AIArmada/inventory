<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources;

use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Resources\AlertRuleResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AlertRuleResource extends Resource
{
    protected static ?string $model = AlertRule::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Alert Rules';

    protected static ?string $modelLabel = 'Alert Rule';

    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cart.navigation_group', 'E-Commerce');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000),

                        Forms\Components\Select::make('event_type')
                            ->required()
                            ->options([
                                'abandonment' => 'Cart Abandonment',
                                'fraud' => 'Fraud Detection',
                                'high_value' => 'High-Value Cart',
                                'recovery' => 'Recovery Opportunity',
                                'custom' => 'Custom Event',
                            ]),

                        Forms\Components\Select::make('severity')
                            ->required()
                            ->default('info')
                            ->options([
                                'info' => 'Info',
                                'warning' => 'Warning',
                                'critical' => 'Critical',
                            ]),

                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority rules are evaluated first'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Conditions')
                    ->description('Define when this alert should trigger')
                    ->schema([
                        Forms\Components\Repeater::make('conditions.all')
                            ->label('All conditions must match (AND)')
                            ->schema([
                                Forms\Components\TextInput::make('field')
                                    ->required()
                                    ->placeholder('e.g., cart_value_cents'),

                                Forms\Components\Select::make('operator')
                                    ->required()
                                    ->default('>=')
                                    ->options([
                                        '=' => 'Equals (=)',
                                        '!=' => 'Not Equals (!=)',
                                        '>' => 'Greater Than (>)',
                                        '>=' => 'Greater Than or Equal (>=)',
                                        '<' => 'Less Than (<)',
                                        '<=' => 'Less Than or Equal (<=)',
                                        'in' => 'In Array',
                                        'not_in' => 'Not In Array',
                                        'contains' => 'Contains',
                                        'is_null' => 'Is Null',
                                        'is_not_null' => 'Is Not Null',
                                        'between' => 'Between',
                                    ]),

                                Forms\Components\TextInput::make('value')
                                    ->placeholder('Value to compare'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Condition'),
                    ]),

                Forms\Components\Section::make('Notification Channels')
                    ->schema([
                        Forms\Components\Toggle::make('notify_database')
                            ->label('In-App Notifications')
                            ->default(true),

                        Forms\Components\Toggle::make('notify_email')
                            ->label('Email Notifications')
                            ->live(),

                        Forms\Components\TagsInput::make('email_recipients')
                            ->label('Email Recipients')
                            ->placeholder('Add email address')
                            ->visible(fn (Forms\Get $get) => $get('notify_email')),

                        Forms\Components\Toggle::make('notify_slack')
                            ->label('Slack Notifications')
                            ->live(),

                        Forms\Components\TextInput::make('slack_webhook_url')
                            ->label('Slack Webhook URL')
                            ->url()
                            ->visible(fn (Forms\Get $get) => $get('notify_slack')),

                        Forms\Components\Toggle::make('notify_webhook')
                            ->label('Webhook Notifications')
                            ->live(),

                        Forms\Components\TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->visible(fn (Forms\Get $get) => $get('notify_webhook')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Throttling')
                    ->schema([
                        Forms\Components\TextInput::make('cooldown_minutes')
                            ->label('Cooldown Period (minutes)')
                            ->numeric()
                            ->default(60)
                            ->helperText('Minimum time between alerts of this type'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fraud' => 'danger',
                        'abandonment' => 'warning',
                        'high_value' => 'info',
                        'recovery' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('channels')
                    ->label('Channels')
                    ->state(function (AlertRule $record): string {
                        $channels = [];
                        if ($record->notify_database) {
                            $channels[] = 'App';
                        }
                        if ($record->notify_email) {
                            $channels[] = 'Email';
                        }
                        if ($record->notify_slack) {
                            $channels[] = 'Slack';
                        }
                        if ($record->notify_webhook) {
                            $channels[] = 'Webhook';
                        }

                        return implode(', ', $channels) ?: 'None';
                    }),

                Tables\Columns\TextColumn::make('cooldown_minutes')
                    ->label('Cooldown')
                    ->suffix(' min'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('logs_count')
                    ->label('Alerts')
                    ->counts('logs'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'abandonment' => 'Cart Abandonment',
                        'fraud' => 'Fraud Detection',
                        'high_value' => 'High-Value Cart',
                        'recovery' => 'Recovery Opportunity',
                        'custom' => 'Custom Event',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Test Alert Rule')
                    ->modalDescription('This will send a test alert to all configured channels.')
                    ->action(function (AlertRule $record) {
                        // Dispatch test alert
                        $dispatcher = app(\AIArmada\FilamentCart\Services\AlertDispatcher::class);
                        $event = \AIArmada\FilamentCart\Data\AlertEvent::custom(
                            eventType: $record->event_type,
                            severity: 'info',
                            title: "[TEST] {$record->name}",
                            message: 'This is a test alert to verify your notification channels are working.',
                            data: ['test' => true, 'rule_id' => $record->id],
                        );
                        $dispatcher->dispatch($record, $event);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlertRules::route('/'),
            'create' => Pages\CreateAlertRule::route('/create'),
            'view' => Pages\ViewAlertRule::route('/{record}'),
            'edit' => Pages\EditAlertRule::route('/{record}/edit'),
        ];
    }
}
