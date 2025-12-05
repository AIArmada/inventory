<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\RecurringScheduleResource\Pages;

use AIArmada\FilamentChip\Resources\Pages\ReadOnlyViewRecord;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewRecurringSchedule extends ReadOnlyViewRecord
{
    protected static string $resource = RecurringScheduleResource::class;

    #[Override]
    public function getTitle(): string
    {
        $record = $this->getRecord();

        return sprintf('Schedule %s', substr($record->id, 0, 8));
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedArrowPath;
    }
}
