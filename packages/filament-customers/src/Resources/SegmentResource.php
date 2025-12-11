<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources;

use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SegmentResource extends Resource
{
    protected static ?string $model = Segment::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Segment Information')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Segment Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(
                                        fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))
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

                        Forms\Components\Section::make('Assignment Rules')
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
                                                'lifetime_value_min' => 'Minimum Lifetime Value',
                                                'lifetime_value_max' => 'Maximum Lifetime Value',
                                                'total_orders_min' => 'Minimum Total Orders',
                                                'total_orders_max' => 'Maximum Total Orders',
                                                'last_order_days' => 'Ordered in Last X Days',
                                                'no_order_days' => 'No Order for X Days',
                                                'accepts_marketing' => 'Accepts Marketing',
                                                'is_tax_exempt' => 'Tax Exempt',
                                            ])
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('value')
                                            ->label('Value')
                                            ->required()
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => ! in_array($get('field'), ['accepts_marketing', 'is_tax_exempt'])),

                                        Forms\Components\Toggle::make('value')
                                            ->label('Value')
                                            ->visible(fn (Forms\Get $get) => in_array($get('field'), ['accepts_marketing', 'is_tax_exempt'])),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add Condition')
                                    ->visible(fn (Forms\Get $get) => $get('is_automatic') === true),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Settings')
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

                        Forms\Components\Section::make('Manual Assignment')
                            ->schema([
                                Forms\Components\Select::make('customers')
                                    ->label('Customers')
                                    ->relationship('customers', 'email')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('For manual segments only'),
                            ])
                            ->visible(fn (Forms\Get $get) => ! $get('is_automatic')),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('rebuild')
                    ->label('Rebuild')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->is_automatic)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $count = $record->rebuildCustomerList();

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Segment Rebuilt')
                            ->body("{$count} customer(s) now in this segment.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
