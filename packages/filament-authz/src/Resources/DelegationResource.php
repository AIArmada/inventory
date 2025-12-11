<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DelegationResource extends Resource
{
    protected static ?string $model = Delegation::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Delegations';

    protected static string | UnitEnum | null $navigationGroup = 'Authorization';

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Delegation Details')
                    ->schema([
                        Forms\Components\Select::make('delegator_id')
                            ->label('From User (Delegator)')
                            ->relationship('delegator', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('delegatee_id')
                            ->label('To User (Delegatee)')
                            ->relationship('delegatee', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->different('delegator_id'),

                        Forms\Components\TextInput::make('permission')
                            ->label('Permission')
                            ->required()
                            ->placeholder('e.g., user.view or user.*'),

                        Forms\Components\Toggle::make('can_redelegate')
                            ->label('Can Re-delegate')
                            ->helperText('Allow the delegatee to further delegate this permission')
                            ->default(false),
                    ]),

                Forms\Components\Section::make('Duration')
                    ->schema([
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->helperText('Leave empty for permanent delegation'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('delegator.name')
                    ->label('From')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('delegatee.name')
                    ->label('To')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('permission')
                    ->label('Permission')
                    ->badge()
                    ->searchable(),

                Tables\Columns\IconColumn::make('can_redelegate')
                    ->label('Can Re-delegate')
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn (Delegation $record): bool => $record->isActive()),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(fn (Builder $query) => $query->whereNull('revoked_at')->where(function ($q): void {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    }))
                    ->toggle()
                    ->default(),

                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->query(
                        fn (Builder $query) => $query
                            ->where('expires_at', '<=', now()->addDays(7))
                            ->where('expires_at', '>', now())
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(
                        fn (Builder $query) => $query
                            ->where('expires_at', '<', now())
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Delegation $record) => $record->revoke())
                    ->visible(fn (Delegation $record) => $record->isActive()),

                Tables\Actions\Action::make('extend')
                    ->label('Extend')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->form([
                        Forms\Components\DateTimePicker::make('new_expires_at')
                            ->label('New Expiration Date')
                            ->required()
                            ->minDate(now()),
                    ])
                    ->action(
                        fn (Delegation $record, array $data) => $record->update(['expires_at' => $data['new_expires_at']])
                    )
                    ->visible(fn (Delegation $record) => $record->isActive()),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('revoke_all')
                        ->label('Revoke Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->revoke()),

                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListDelegations::route('/'),
            'create' => Pages\CreateDelegation::route('/create'),
            'view' => Pages\ViewDelegation::route('/{record}'),
            'edit' => Pages\EditDelegation::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereNull('revoked_at')
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function canAccess(): bool
    {
        return config('filament-authz.enterprise.delegation.enabled', false);
    }
}
