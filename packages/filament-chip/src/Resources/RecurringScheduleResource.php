<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Models\RecurringSchedule;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages\ListRecurringSchedules;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages\ViewRecurringSchedule;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\RelationManagers\ChargesRelationManager;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Schemas\RecurringScheduleInfolist;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource\Tables\RecurringScheduleTable;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class RecurringScheduleResource extends BaseChipResource
{
    protected static ?string $model = RecurringSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $modelLabel = 'Recurring Schedule';

    protected static ?string $pluralModelLabel = 'Recurring Schedules';

    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    public static function table(Table $table): Table
    {
        return RecurringScheduleTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return RecurringScheduleInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'chip_client_id',
            'recurring_token_id',
            'currency',
        ];
    }

    public static function getRelations(): array
    {
        return [
            ChargesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringSchedules::route('/'),
            'view' => ViewRecurringSchedule::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'recurring';
    }
}
