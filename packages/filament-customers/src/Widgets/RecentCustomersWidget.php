<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Widgets;

use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentCustomersWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Customers';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CustomersOwnerScope::applyToOwnedQuery(Customer::query())
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Customer')
                    ->description(fn ($record) => $record->email),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),

                Tables\Columns\IconColumn::make('accepts_marketing')
                    ->label('Marketing')
                    ->boolean(),

                Tables\Columns\TextColumn::make('segments.name')
                    ->label('Segments')
                    ->badge()
                    ->placeholder('None'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
