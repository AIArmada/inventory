<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources;

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): ?string
    {
        $count = CustomersOwnerScope::applyToOwnedQuery(static::getModel()::query())
            ->where('status', CustomerStatus::Active)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<Customer>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Customer> $query */
        $query = parent::getEloquentQuery();

        return CustomersOwnerScope::applyToOwnedQuery($query);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Customer Information')
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        $owner = CustomersOwnerScope::resolveOwner();
                                        if ($owner !== null) {
                                            return $rule
                                                ->where('owner_type', $owner->getMorphClass())
                                                ->where('owner_id', $owner->getKey());
                                        }

                                        return $rule
                                            ->whereNull('owner_type')
                                            ->whereNull('owner_id');
                                    })
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(20),

                                Forms\Components\TextInput::make('company')
                                    ->label('Company')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        Section::make('Preferences')
                            ->schema([
                                Forms\Components\Toggle::make('accepts_marketing')
                                    ->label('Accepts Marketing')
                                    ->helperText('Customer has opted in for marketing emails'),

                                Forms\Components\Toggle::make('is_tax_exempt')
                                    ->label('Tax Exempt'),

                                Forms\Components\Textarea::make('tax_exempt_reason')
                                    ->label('Tax Exempt Reason')
                                    ->rows(2)
                                    ->visible(fn (Get $get) => $get('is_tax_exempt')),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options(
                                        collect(CustomerStatus::cases())
                                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                                    )
                                    ->required()
                                    ->default('active'),
                            ]),

                        Section::make('Segments')
                            ->schema([
                                Forms\Components\Select::make('segments')
                                    ->label('Segments')
                                    ->relationship(
                                        name: 'segments',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => CustomersOwnerScope::applyToOwnedQuery($query),
                                    )
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->helperText('Manual segment assignment'),
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
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn ($record) => $record->email),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\IconColumn::make('accepts_marketing')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_tax_exempt')
                    ->label('Tax Exempt')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('segments.name')
                    ->label('Segments')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(
                        collect(CustomerStatus::cases())
                            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
                    ),

                Tables\Filters\TernaryFilter::make('accepts_marketing')
                    ->label('Accepts Marketing'),

                Tables\Filters\TernaryFilter::make('is_tax_exempt')
                    ->label('Tax Exempt'),

                Tables\Filters\SelectFilter::make('segments')
                    ->relationship(
                        name: 'segments',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => CustomersOwnerScope::applyToOwnedQuery($query),
                    )
                    ->multiple()
                    ->preload(),

                Tables\Filters\Filter::make('recent')
                    ->label('Active (Last 30 days)')
                    ->query(fn ($query) => $query->where('last_login_at', '>=', CarbonImmutable::now()->subDays(30))),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('opt_in_marketing')
                        ->label('Opt-In Marketing')
                        ->icon('heroicon-o-bell')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $user = \Filament\Facades\Filament::auth()->user();
                            abort_unless($user !== null, 403);

                            $records->each(function (Customer $record) use ($user): void {
                                Gate::forUser($user)->authorize('update', $record);
                                $record->optInMarketing();
                            });
                        }),
                    \Filament\Actions\BulkAction::make('opt_out_marketing')
                        ->label('Opt-Out Marketing')
                        ->icon('heroicon-o-bell-slash')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $user = \Filament\Facades\Filament::auth()->user();
                            abort_unless($user !== null, 403);

                            $records->each(function (Customer $record) use ($user): void {
                                Gate::forUser($user)->authorize('update', $record);
                                $record->optOutMarketing();
                            });
                        }),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Customer Overview')
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Name'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label('Phone')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(4),

                Section::make('Preferences')
                    ->schema([
                        TextEntry::make('accepts_marketing')
                            ->label('Accepts Marketing')
                            ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                        TextEntry::make('is_tax_exempt')
                            ->label('Tax Exempt')
                            ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn ($state) => $state ? 'warning' : 'gray'),
                        TextEntry::make('tax_exempt_reason')
                            ->label('Tax Exempt Reason')
                            ->placeholder('N/A'),
                    ])
                    ->columns(3),

                Section::make('Activity')
                    ->schema([
                        TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('created_at')
                            ->label('Customer Since')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Segments')
                    ->schema([
                        TextEntry::make('segments.name')
                            ->label('Assigned Segments')
                            ->badge()
                            ->placeholder('No segments'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AddressesRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'phone', 'company'];
    }
}
