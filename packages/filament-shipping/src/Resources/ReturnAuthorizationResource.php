<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentShipping\Actions\ApproveReturnAction;
use AIArmada\FilamentShipping\Actions\RejectReturnAction;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\Pages;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers;
use AIArmada\Shipping\Enums\ReturnReason;
use AIArmada\Shipping\Models\ReturnAuthorization;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReturnAuthorizationResource extends Resource
{
    protected static ?string $model = ReturnAuthorization::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string | UnitEnum | null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Returns';

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ReturnAuthorization> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        /** @var Builder<ReturnAuthorization> $scoped */
        $scoped = $query->forOwner($owner, includeGlobal: true);

        return $scoped;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Return Details')
                    ->schema([
                        Forms\Components\TextInput::make('rma_number')
                            ->label('RMA Number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('order_reference')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'received' => 'Received',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->options([
                                'refund' => 'Refund',
                                'exchange' => 'Exchange',
                                'store_credit' => 'Store Credit',
                            ])
                            ->required(),

                        Forms\Components\Select::make('reason')
                            ->options(collect(ReturnReason::cases())
                                ->mapWithKeys(fn ($reason) => [$reason->value => $reason->getLabel()]))
                            ->required(),

                        Forms\Components\Textarea::make('reason_details')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Timeline')
                    ->schema([
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('received_at')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('completed_at')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('expires_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rma_number')
                    ->label('RMA #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'received' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->badge(),

                Tables\Columns\TextColumn::make('reason')
                    ->formatStateUsing(fn ($state) => ReturnReason::tryFrom($state)?->getLabel() ?? $state),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'received' => 'Received',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'refund' => 'Refund',
                        'exchange' => 'Exchange',
                        'store_credit' => 'Store Credit',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                ApproveReturnAction::make(),

                RejectReturnAction::make(),
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
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReturnAuthorizations::route('/'),
            'create' => Pages\CreateReturnAuthorization::route('/create'),
            'view' => Pages\ViewReturnAuthorization::route('/{record}'),
            'edit' => Pages\EditReturnAuthorization::route('/{record}/edit'),
        ];
    }
}
