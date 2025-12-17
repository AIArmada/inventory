<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages\ListJntTrackingEvents;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages\ViewJntTrackingEvent;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Schemas\JntTrackingEventInfolist;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Tables\JntTrackingEventTable;
use AIArmada\Jnt\Models\JntTrackingEvent;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

final class JntTrackingEventResource extends BaseJntResource
{
    protected static ?string $model = JntTrackingEvent::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'Tracking Event';

    protected static ?string $pluralModelLabel = 'Tracking Events';

    protected static ?string $recordTitleAttribute = 'tracking_number';

    #[Override]
    public static function table(Table $table): Table
    {
        return JntTrackingEventTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return JntTrackingEventInfolist::configure($schema);
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = parent::getEloquentQuery();

        // JntTrackingEvent doesn't have owner columns; scope through the parent order.
        if (! config('jnt.owner.enabled', false)) {
            return $query;
        }

        $owner = null;
        if (app()->bound(OwnerResolverInterface::class)) {
            $owner = app(OwnerResolverInterface::class)->resolve();
        }

        /** @var bool $includeGlobal */
        $includeGlobal = (bool) config('jnt.owner.include_global', true);

        return $query->whereHas('order', function (Builder $orderQuery) use ($owner, $includeGlobal): void {
            $model = $orderQuery->getModel();

            if (! method_exists($model, 'scopeForOwner')) {
                return;
            }

            call_user_func([$model, 'scopeForOwner'], $orderQuery, $owner, $includeGlobal);
        });
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'tracking_number',
            'order_reference',
            'scan_type_name',
            'description',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJntTrackingEvents::route('/'),
            'view' => ViewJntTrackingEvent::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'tracking_events';
    }
}
