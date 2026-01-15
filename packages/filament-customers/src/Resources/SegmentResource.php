<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources;

use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Policies\SegmentPolicy;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SegmentResource extends Resource
{
    protected static ?string $model = Segment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $count = CustomersOwnerScope::applyToOwnedQuery(static::getModel()::query())
            ->where('is_active', true)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<Segment>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Segment> $query */
        $query = parent::getEloquentQuery();

        return CustomersOwnerScope::applyToOwnedQuery($query);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Segment Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Segment Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
                                    ),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true),

                                Forms\Components\Select::make('type')
                                    ->label('Type')
                                    ->options(
                                        collect(SegmentType::cases())
                                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                    )
                                    ->required()
                                    ->default('custom'),

                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Assignment Rules')
                            ->schema([
                                Forms\Components\Toggle::make('is_automatic')
                                    ->label('Automatic Assignment')
                                    ->helperText('Automatically assign customers based on conditions')
                                    ->live(),

                                Forms\Components\Repeater::make('conditions')
                                    ->label('Conditions')
                                    ->schema([
                                        Forms\Components\Select::make('field')
                                            ->label('Field')
                                            ->options([
                                                'accepts_marketing' => 'Accepts Marketing',
                                                'is_tax_exempt' => 'Tax Exempt',
                                                'status' => 'Customer Status',
                                                'created_days_ago' => 'Customer for X Days',
                                                'last_login_days' => 'Logged in Last X Days',
                                                'no_login_days' => 'No Login for X Days',
                                            ])
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('value_numeric')
                                            ->label('Value')
                                            ->required()
                                            ->numeric()
                                            ->visible(fn (Get $get) => in_array($get('field'), ['created_days_ago', 'last_login_days', 'no_login_days']))
                                            ->dehydratedWhenHidden(),

                                        Forms\Components\Toggle::make('value_boolean')
                                            ->label('Value')
                                            ->visible(fn (Get $get) => in_array($get('field'), ['accepts_marketing', 'is_tax_exempt']))
                                            ->dehydratedWhenHidden(),

                                        Forms\Components\Select::make('value_status')
                                            ->label('Status')
                                            ->options(
                                                collect(\AIArmada\Customers\Enums\CustomerStatus::cases())
                                                    ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                            )
                                            ->visible(fn (Get $get) => $get('field') === 'status')
                                            ->dehydratedWhenHidden(),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Condition')
                                    ->visible(fn (Get $get) => $get('is_automatic') === true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = more important for pricing'),
                            ]),

                        Section::make('Manual Assignment')
                            ->schema([
                                Forms\Components\Select::make('customers')
                                    ->label('Customers')
                                    ->relationship(
                                        name: 'customers',
                                        titleAttribute: 'email',
                                        modifyQueryUsing: fn (Builder $query): Builder => CustomersOwnerScope::applyToOwnedQuery($query),
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('For manual segments only'),
                            ])
                            ->visible(fn (Get $get) => ! $get('is_automatic')),
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
                    ->label('Segment')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Customers')
                    ->counts('customers')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_automatic')
                    ->label('Auto')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(
                        collect(SegmentType::cases())
                            ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                    ),

                Tables\Filters\TernaryFilter::make('is_automatic')
                    ->label('Assignment Type')
                    ->trueLabel('Automatic')
                    ->falseLabel('Manual'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\Action::make('rebuild')
                    ->label('Rebuild')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->is_automatic)
                    ->requiresConfirmation()
                    ->action(function (Segment $record): void {
                        $user = Auth::user();
                        abort_unless($user !== null, 403);

                        $policy = new SegmentPolicy;
                        abort_unless($policy->rebuild($user, $record), 403);

                        $count = $record->rebuildCustomerList();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Segment Rebuilt')
                            ->body("{$count} customer(s) now in this segment.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSegments::route('/'),
            'create' => Pages\CreateSegment::route('/create'),
            'view' => Pages\ViewSegment::route('/{record}'),
            'edit' => Pages\EditSegment::route('/{record}/edit'),
        ];
    }
}
