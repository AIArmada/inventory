<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Client;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ListClients;
use AIArmada\FilamentChip\Resources\ClientResource\Pages\ViewClient;
use AIArmada\FilamentChip\Resources\ClientResource\Schemas\ClientInfolist;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class ClientResource extends BaseChipResource
{
    protected static ?string $model = Client::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Client';

    protected static ?string $pluralModelLabel = 'Clients';

    protected static ?string $recordTitleAttribute = 'email';

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('full_name')
                            ->label('Client')
                            ->icon('heroicon-o-user-circle')
                            ->weight(FontWeight::SemiBold)
                            ->searchable()
                            ->wrap()
                            ->placeholder('—'),
                        TextColumn::make('email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->searchable()
                            ->wrap(),
                        TextColumn::make('phone')
                            ->label('Phone')
                            ->icon('heroicon-o-phone')
                            ->placeholder('—'),
                    ])->carded(), // @phpstan-ignore method.notFound
                    Panel::make([
                        Stack::make([
                            TextColumn::make('location')
                                ->label('Billing Location')
                                ->icon('heroicon-o-map-pin')
                                ->placeholder('—'),
                            TextColumn::make('shipping_location')
                                ->label('Shipping Location')
                                ->icon('heroicon-o-truck')
                                ->placeholder('—'),
                            TextColumn::make('country')
                                ->label('Country')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null && $state !== '' && $state !== '0' ? mb_strtoupper($state) : null)
                                ->placeholder('—'),
                        ])->carded(), // @phpstan-ignore method.notFound
                    ])->softShadow(), // @phpstan-ignore method.notFound
                    Stack::make([
                        TextColumn::make('created_on')
                            ->label('Created')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->since()
                            ->icon('heroicon-o-clock'),
                        TextColumn::make('updated_on')
                            ->label('Updated')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->placeholder('—')
                            ->icon('heroicon-o-arrow-path'),
                        TextColumn::make('registration_number')
                            ->label('Registration #')
                            ->icon('heroicon-o-identification')
                            ->placeholder('—'),
                        TextColumn::make('tax_number')
                            ->label('Tax #')
                            ->icon('heroicon-o-identification')
                            ->placeholder('—'),
                    ])->carded(), // @phpstan-ignore method.notFound
                ])->glow(), // @phpstan-ignore method.notFound
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('Country')
                    ->options(function (): array {
                        $query = Client::query();

                        if (method_exists($query->getModel(), 'scopeForOwner')) {
                            $query->forOwner();
                        }

                        return $query
                            ->select('country')
                            ->distinct()
                            ->whereNotNull('country')
                            ->orderBy('country')
                            ->pluck('country', 'country')
                            ->mapWithKeys(fn (string $country): array => [$country => mb_strtoupper($country)])
                            ->all();
                    })
                    ->searchable(),
                Filter::make('has_phone')
                    ->label('Has Phone')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('phone')
                        ->where('phone', '!=', '')),
                Filter::make('has_shipping')
                    ->label('Has Shipping Address')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $subQuery): void {
                        $subQuery
                            ->whereNotNull('shipping_street_address')
                            ->orWhereNotNull('shipping_city')
                            ->orWhereNotNull('shipping_country');
                    })),
                Filter::make('has_company_details')
                    ->label('Has Company Details')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $subQuery): void {
                        $subQuery
                            ->whereNotNull('legal_name')
                            ->orWhereNotNull('brand_name')
                            ->orWhereNotNull('registration_number')
                            ->orWhereNotNull('tax_number');
                    })),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_on', 'desc')
            ->paginated([25, 50, 100])
            ->poll(self::pollingInterval());
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ClientInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'email',
            'full_name',
            'phone',
            'legal_name',
            'brand_name',
            'registration_number',
            'tax_number',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'view' => ViewClient::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'clients';
    }
}
