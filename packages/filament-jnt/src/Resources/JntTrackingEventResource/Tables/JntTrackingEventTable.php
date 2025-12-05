<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Tables;

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntTrackingEvent;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class JntTrackingEventTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->icon('heroicon-o-truck')
                    ->iconColor('primary')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('order_reference')
                    ->label('Order Ref')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('scan_type_code')
                    ->label('Status')
                    ->badge()
                    ->icon(fn (JntTrackingEvent $record): string => $record->getNormalizedStatus()->icon())
                    ->color(fn (JntTrackingEvent $record): string => $record->getNormalizedStatus()->color())
                    ->formatStateUsing(fn (JntTrackingEvent $record): string => $record->getNormalizedStatus()->label())
                    ->sortable(),
                TextColumn::make('scan_time')
                    ->label('Scan Time')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->searchable()
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('scan_network_name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('scan_network_city')
                    ->label('City')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                IconColumn::make('problem_type')
                    ->label('Problem')
                    ->icon(fn (?string $state): string => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->color(fn (?string $state): string => $state ? 'danger' : 'success')
                    ->sortable(),
                TextColumn::make('staff_name')
                    ->label('Staff')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-jnt.tables.datetime_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('normalized_status')
                    ->label('Status')
                    ->options(TrackingStatus::class)
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        $status = TrackingStatus::from($data['value']);

                        return match ($status) {
                            TrackingStatus::Pending => $query->whereNull('scan_type_code'),
                            TrackingStatus::Delivered => $query->where('scan_type_code', '100'),
                            TrackingStatus::Exception => $query->whereNotNull('problem_type'),
                            TrackingStatus::InTransit => $query->whereIn('scan_type_code', ['20', '30', '401', '402']),
                            TrackingStatus::AtHub => $query->whereIn('scan_type_code', ['403', '404', '405']),
                            TrackingStatus::OutForDelivery => $query->where('scan_type_code', '94'),
                            TrackingStatus::PickedUp => $query->whereIn('scan_type_code', ['10', '400']),
                            TrackingStatus::ReturnInitiated => $query->where('scan_type_code', '172'),
                            TrackingStatus::Returned => $query->where('scan_type_code', '173'),
                            TrackingStatus::DeliveryAttempted => $query->where('scan_type_code', '110'),
                        };
                    }),
                Filter::make('has_problem')
                    ->label('Has Problem')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('problem_type')),
                Filter::make('delivered')
                    ->label('Delivered')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('scan_type_code', '100')),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('scan_time', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-jnt.polling_interval', '30s'));
    }
}
