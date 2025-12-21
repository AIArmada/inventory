<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
                        ->relationship('affiliate', 'name', modifyQueryUsing: function (Builder $affiliateQuery): Builder {
                            if (! (bool) config('affiliates.owner.enabled', false)) {
                                return $affiliateQuery;
                            }

                            /** @var Model|null $owner */
                            $owner = OwnerContext::resolve();
                            $includeGlobal = (bool) config('affiliates.owner.include_global', false);

                            $scoped = $affiliateQuery->withoutGlobalScope(OwnerScope::class);

                            return OwnerQuery::applyToEloquentBuilder($scoped, $owner, $includeGlobal);
                        })
                        ->disabled(),

                    Forms\Components\TextInput::make('rule_code')
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
                    Forms\Components\TextInput::make('risk_points')
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
                    Forms\Components\Textarea::make('evidence.review_notes')
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

                Tables\Columns\TextColumn::make('rule_code')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\BadgeColumn::make('severity')
                    ->colors([
                        'gray' => FraudSeverity::Low->value,
                        'warning' => FraudSeverity::Medium->value,
                        'danger' => fn ($state) => in_array($state, [FraudSeverity::High->value, FraudSeverity::Critical->value]),
                    ]),

                Tables\Columns\TextColumn::make('risk_points')
                    ->label('Risk')
                    ->formatStateUsing(fn (int $state): string => $state . '%')
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

                Tables\Filters\SelectFilter::make('rule_code')
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
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->visible(fn ($record) => $record->status === FraudSignalStatus::Detected)
                    ->action(function (AffiliateFraudSignal $record): void {
                        Gate::authorize('update', $record);

                        $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $signal->dismiss($reviewedBy === null ? null : (string) $reviewedBy);
                    }),
                Action::make('confirm')
                    ->icon('heroicon-o-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->visible(fn ($record) => $record->status === FraudSignalStatus::Detected)
                    ->action(function (AffiliateFraudSignal $record): void {
                        Gate::authorize('update', $record);

                        $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                            ->whereKey($record->getKey())
                            ->firstOrFail();

                        $reviewedBy = auth()->user()?->getAuthIdentifier();

                        $signal->confirm($reviewedBy === null ? null : (string) $reviewedBy);
                    }),
            ])
            ->bulkActions([
                BulkAction::make('dismiss_selected')
                    ->label('Dismiss Selected')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => (Filament::auth()->user() ?? auth()->user())?->can('affiliates.fraud.update') ?? false)
                    ->action(function ($records): void {
                        $reviewedBy = auth()->user()?->getAuthIdentifier();
                        $reviewedBy = $reviewedBy === null ? null : (string) $reviewedBy;

                        $records->each(function (AffiliateFraudSignal $record) use ($reviewedBy): void {
                            Gate::authorize('update', $record);

                            $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                                ->whereKey($record->getKey())
                                ->firstOrFail();

                            $signal->dismiss($reviewedBy);
                        });
                    }),
            ])
            ->defaultSort('detected_at', 'desc');
    }

    /**
     * @return Builder<AffiliateFraudSignal>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateFraudSignal> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $query;
        }

        /** @var Model|null $owner */
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        return $query->whereHas('affiliate', function (Builder $affiliateQuery) use ($owner, $includeGlobal): void {
            $scoped = $affiliateQuery->withoutGlobalScope(OwnerScope::class);
            OwnerQuery::applyToEloquentBuilder($scoped, $owner, $includeGlobal);
        });
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
        $query = self::getModel()::query()->where('status', FraudSignalStatus::Detected);

        if ((bool) config('affiliates.owner.enabled', false)) {
            /** @var Model|null $owner */
            $owner = OwnerContext::resolve();
            $includeGlobal = (bool) config('affiliates.owner.include_global', false);

            $query->whereHas('affiliate', function (Builder $affiliateQuery) use ($owner, $includeGlobal): void {
                $scoped = $affiliateQuery->withoutGlobalScope(OwnerScope::class);
                OwnerQuery::applyToEloquentBuilder($scoped, $owner, $includeGlobal);
            });
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
