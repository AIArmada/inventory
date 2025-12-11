<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

final class AffiliateFraudSignalResource extends Resource
{
    protected static ?string $model = AffiliateFraudSignal::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string | UnitEnum | null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Fraud Signals';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Signal Details')
                ->schema([
                    Forms\Components\Select::make('affiliate_id')
                        ->relationship('affiliate', 'name')
                        ->disabled(),

                    Forms\Components\TextInput::make('signal_type')
                        ->disabled(),

                    Forms\Components\Select::make('severity')
                        ->options(FraudSeverity::class)
                        ->disabled(),

                    Forms\Components\Select::make('status')
                        ->options(FraudSignalStatus::class)
                        ->required(),
                ])
                ->columns(2),

            Section::make('Detection')
                ->schema([
                    Forms\Components\TextInput::make('score')
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('detected_at')
                        ->disabled(),

                    Forms\Components\Textarea::make('description')
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Review Notes')
                ->schema([
                    Forms\Components\Textarea::make('review_notes')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('reviewed_by')
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('reviewed_at')
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('affiliate.name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('signal_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\BadgeColumn::make('severity')
                    ->colors([
                        'gray' => FraudSeverity::Low->value,
                        'warning' => FraudSeverity::Medium->value,
                        'danger' => fn ($state) => in_array($state, [FraudSeverity::High->value, FraudSeverity::Critical->value]),
                    ]),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => FraudSignalStatus::Detected->value,
                        'info' => FraudSignalStatus::Reviewed->value,
                        'gray' => FraudSignalStatus::Dismissed->value,
                        'danger' => FraudSignalStatus::Confirmed->value,
                    ]),

                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->description),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(FraudSignalStatus::class),

                Tables\Filters\SelectFilter::make('severity')
                    ->options(FraudSeverity::class),

                Tables\Filters\SelectFilter::make('signal_type')
                    ->options([
                        'velocity' => 'Velocity',
                        'pattern' => 'Pattern',
                        'geo_anomaly' => 'Geo Anomaly',
                        'self_referral' => 'Self Referral',
                        'suspicious_conversion' => 'Suspicious Conversion',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === FraudSignalStatus::Detected)
                    ->action(fn ($record) => $record->update([
                        'status' => FraudSignalStatus::Dismissed,
                        'reviewed_at' => now(),
                    ])),
                Action::make('confirm')
                    ->icon('heroicon-o-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === FraudSignalStatus::Detected)
                    ->action(fn ($record) => $record->update([
                        'status' => FraudSignalStatus::Confirmed,
                        'reviewed_at' => now(),
                    ])),
            ])
            ->bulkActions([
                BulkAction::make('dismiss_selected')
                    ->label('Dismiss Selected')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update([
                        'status' => FraudSignalStatus::Dismissed,
                        'reviewed_at' => now(),
                    ])),
            ])
            ->defaultSort('detected_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => AffiliateFraudSignalResource\Pages\ListAffiliateFraudSignals::route('/'),
            'view' => AffiliateFraudSignalResource\Pages\ViewAffiliateFraudSignal::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getModel()::where('status', FraudSignalStatus::Detected)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
