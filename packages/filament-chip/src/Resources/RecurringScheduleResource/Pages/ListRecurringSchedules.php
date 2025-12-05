<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages;

use AIArmada\FilamentChip\Resources\RecurringScheduleResource;
use Filament\Resources\Pages\ListRecords;

final class ListRecurringSchedules extends ListRecords
{
    protected static string $resource = RecurringScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
