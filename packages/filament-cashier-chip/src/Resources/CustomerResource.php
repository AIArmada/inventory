<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources;

use AIArmada\CashierChip\Cashier;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages\ListCustomers;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages\ViewCustomer;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers\PaymentMethodsRelationManager;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers\SubscriptionsRelationManager;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Schemas\CustomerInfolist;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables\CustomerTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class CustomerResource extends BaseCashierChipResource
{
    protected static ?string $model = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.customer.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.customer.plural');
    }

    public static function getModel(): string
    {
        return Cashier::$customerModel;
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return CustomerTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        $relations = [
            SubscriptionsRelationManager::class,
        ];

        if (config('filament-cashier-chip.features.payment_methods', true)) {
            $relations[] = PaymentMethodsRelationManager::class;
        }

        return $relations;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'email',
            'chip_id',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'customers';
    }
}
