<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources;

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', CustomerStatus::Active)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Customer Information')
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
                                    ->unique(ignoreRecord: true)
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

                        Forms\Components\Section::make('Preferences')
                            ->schema([
                                Forms\Components\Toggle::make('accepts_marketing')
                                    ->label('Accepts Marketing')
                                    ->helperText('Customer has opted in for marketing emails'),

                                Forms\Components\Toggle::make('is_tax_exempt')
                                    ->label('Tax Exempt'),

                                Forms\Components\Textarea::make('tax_exempt_reason')
                                    ->label('Tax Exempt Reason')
                                    ->rows(2)
                                    ->visible(fn (Forms\Get $get) => $get('is_tax_exempt')),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status')
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

                        Forms\Components\Section::make('Wallet')
                            ->schema([
                                Forms\Components\TextInput::make('wallet_balance')
                                    ->label('Balance')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn ($state) => $state / 100)
                                    ->helperText('Use actions to add/deduct credit'),
                            ]),

                        Forms\Components\Section::make('Segments')
                            ->schema([
                                Forms\Components\Select::make('segments')
                                    ->label('Segments')
                                    ->relationship('segments', 'name')
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

                Tables\Columns\TextColumn::make('lifetime_value')
                    ->label('LTV')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('Credit')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('accepts_marketing')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('segments.name')
                    ->label('Segments')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Last Order')
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
                    ->relationship('segments', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\Filter::make('high_value')
                    ->label('High Value (LTV > RM 1,000)')
                    ->query(fn ($query) => $query->where('lifetime_value', '>=', 1000_00)),

                Tables\Filters\Filter::make('recent')
                    ->label('Active (Last 30 days)')
                    ->query(fn ($query) => $query->where('last_login_at', '>=', now()->subDays(30))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('add_credit')
                    ->label('Add Credit')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading('Add Store Credit')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (RM)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix('RM'),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->rows(2),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $amountInCents = (int) ($data['amount'] * 100);
                        $record->addCredit($amountInCents, $data['reason'] ?? null);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Credit Added')
                            ->body("RM {$data['amount']} added to wallet.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('opt_in_marketing')
                        ->label('Opt-In Marketing')
                        ->icon('heroicon-o-bell')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->optInMarketing()
                        ),
                    Tables\Actions\BulkAction::make('opt_out_marketing')
                        ->label('Opt-Out Marketing')
                        ->icon('heroicon-o-bell-slash')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->optOutMarketing()
                        ),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Customer Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('full_name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Phone')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Value Metrics')
                    ->schema([
                        Infolists\Components\TextEntry::make('lifetime_value')
                            ->label('Lifetime Value')
                            ->money('MYR', divideBy: 100)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('total_orders')
                            ->label('Total Orders')
                            ->numeric()
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('average_order_value')
                            ->label('AOV')
                            ->getStateUsing(fn ($record) => $record->getAverageOrderValue())
                            ->money('MYR', divideBy: 100)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('wallet_balance')
                            ->label('Wallet Balance')
                            ->money('MYR', divideBy: 100)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Activity')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_order_at')
                            ->label('Last Order')
                            ->dateTime()
                            ->placeholder('No orders yet'),
                        Infolists\Components\TextEntry::make('last_login_at')
                            ->label('Last Login')
                            ->dateTime()
                            ->placeholder('Never'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Customer Since')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Segments')
                    ->schema([
                        Infolists\Components\TextEntry::make('segments.name')
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
            RelationManagers\WishlistsRelationManager::class,
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
