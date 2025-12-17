<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages\ListJntWebhookLogs;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages\ViewJntWebhookLog;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Schemas\JntWebhookLogInfolist;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Tables\JntWebhookLogTable;
use AIArmada\Jnt\Models\JntWebhookLog;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

final class JntWebhookLogResource extends BaseJntResource
{
    protected static ?string $model = JntWebhookLog::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $modelLabel = 'Webhook Log';

    protected static ?string $pluralModelLabel = 'Webhook Logs';

    protected static ?string $recordTitleAttribute = 'tracking_number';

    #[Override]
    public static function table(Table $table): Table
    {
        return JntWebhookLogTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return JntWebhookLogInfolist::configure($schema);
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = parent::getEloquentQuery();

        // JntWebhookLog doesn't have owner columns; scope through the parent order.
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
            'processing_status',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJntWebhookLogs::route('/'),
            'view' => ViewJntWebhookLog::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'webhook_logs';
    }
}
