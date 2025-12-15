<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
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

        return $table
            ->query(
                Shipment::query()
                    ->forOwner($this->resolveOwner())
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
                Tables\Actions\Action::make('view')
                    ->url(fn (Shipment $record) => ShipmentResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }

    private function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        return app(OwnerResolverInterface::class)->resolve();
    }
}
