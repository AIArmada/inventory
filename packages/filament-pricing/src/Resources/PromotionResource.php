<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Resources\PromotionResource\Pages;
use AIArmada\Pricing\Enums\PromotionType;
use AIArmada\Pricing\Models\Promotion;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-gift';

    protected static string | UnitEnum | null $navigationGroup = 'Pricing';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $count = (int) static::getEloquentQuery()->where('is_active', true)->count();

        return $count ? (string) $count : null;
    }

    /**
     * @return Builder<Promotion>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Promotion> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();

        /** @var Builder<Promotion> $scoped */
        $scoped = $query->forOwner(
            $owner,
            (bool) config('pricing.features.owner.include_global', false),
        );

        return $scoped;
    }

    private static function resolveOwner(): ?Model
    {
        if (! (bool) config('pricing.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Promotion Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Promotion Name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label('Coupon Code')
                                    ->helperText('Optional code for coupon-based promotions')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Discount')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Discount Type')
                                    ->options(
                                        collect(PromotionType::cases())
                                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                    )
                                    ->required()
                                    ->default('percentage')
                                    ->live(),

                                Forms\Components\TextInput::make('discount_value')
                                    ->label(fn (Forms\Get $get) => match ($get('type')) {
                                        'percentage' => 'Discount Percentage (%)',
                                        'fixed' => 'Discount Amount (cents)',
                                        default => 'Value',
                                    })
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('min_purchase_amount')
                                    ->label('Minimum Purchase (cents)')
                                    ->numeric()
                                    ->helperText('Minimum order value to apply'),

                                Forms\Components\TextInput::make('min_quantity')
                                    ->label('Minimum Quantity')
                                    ->numeric()
                                    ->helperText('Minimum items in cart'),
                            ])
                            ->columns(2),

                        Section::make('Scheduling')
                            ->schema([
                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label('Start Date'),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label('End Date'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_stackable')
                                    ->label('Stackable')
                                    ->helperText('Can combine with other promotions'),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = apply first'),
                            ]),

                        Section::make('Usage Limits')
                            ->schema([
                                Forms\Components\TextInput::make('usage_limit')
                                    ->label('Total Uses')
                                    ->numeric()
                                    ->helperText('Leave empty for unlimited'),

                                Forms\Components\TextInput::make('per_customer_limit')
                                    ->label('Uses Per Customer')
                                    ->numeric()
                                    ->helperText('Leave empty for unlimited'),

                                Forms\Components\Placeholder::make('usage_count')
                                    ->label('Times Used')
                                    ->content(fn ($record) => $record?->usage_count ?? 0),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Promotion')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->code),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state, $record) => $record->type->formatValue($state)),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Uses')
                    ->numeric()
                    ->alignEnd()
                    ->formatStateUsing(
                        fn ($state, $record) => $record->usage_limit
                        ? "{$state}/{$record->usage_limit}"
                        : $state
                    ),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->placeholder('Always'),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->placeholder('Never'),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(
                        collect(PromotionType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    ),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->authorize(fn (): bool => static::canCreate())
                    ->action(function (Promotion $record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Copy)';
                        $new->code = null;
                        $new->usage_count = 0;
                        $new->save();

                        return redirect(static::getUrl('edit', ['record' => $new]));
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'view' => Pages\ViewPromotion::route('/{record}'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
