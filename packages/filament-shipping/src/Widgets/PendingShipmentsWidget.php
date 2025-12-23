<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class PendingShipmentsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        return 'Pending Shipments';
    }

    public function table(Table $table): Table
    {
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->forOwner($owner, includeGlobal: true);
            }
        }

        return $table
            ->query(
                $query
                    ->where('status', ShipmentStatus::Pending)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),

                Tables\Columns\TextColumn::make('carrier_code')
                    ->label('Carrier')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ShipmentStatus $state) => $state->getColor()),

                Tables\Columns\TextColumn::make('total_weight')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '-'
                        : ($weightUnit === 'kg'
                            ? number_format($state / 1000, 2) . ' kg'
                            : number_format($state) . ' g')),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (Shipment $record) => ShipmentResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
