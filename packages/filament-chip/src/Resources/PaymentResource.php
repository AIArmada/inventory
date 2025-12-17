<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Payment;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ListPayments;
use AIArmada\FilamentChip\Resources\PaymentResource\Pages\ViewPayment;
use AIArmada\FilamentChip\Resources\PaymentResource\Schemas\PaymentInfolist;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
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

final class PaymentResource extends BaseChipResource
{
    protected static ?string $model = Payment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Payments';

    protected static ?string $recordTitleAttribute = 'id';

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('id')
                            ->label('Payment #')
                            ->copyable()
                            ->searchable()
                            ->icon('heroicon-o-hashtag'),
                        TextColumn::make('purchase_id')
                            ->label('Purchase')
                            ->searchable()
                            ->icon('heroicon-o-receipt-refund'),
                        TextColumn::make('payment_type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'refund' => 'warning',
                                'payout' => 'info',
                                default => 'primary',
                            }),
                    ])->carded(),
                    Panel::make([
                        Stack::make([
                            TextColumn::make('formatted_amount')
                                ->label('Amount')
                                ->badge()
                                ->color('primary')
                                ->weight(FontWeight::SemiBold),
                            TextColumn::make('formatted_net_amount')
                                ->label('Net Amount')
                                ->icon('heroicon-o-banknotes')
                                ->placeholder('—'),
                            TextColumn::make('formatted_fee_amount')
                                ->label('Fees')
                                ->icon('heroicon-o-receipt-percent')
                                ->color('warning')
                                ->placeholder('—'),
                        ])->carded(), // @phpstan-ignore method.notFound
                    ])->softShadow(), // @phpstan-ignore method.notFound
                    Stack::make([
                        TextColumn::make('currency')
                            ->label('Currency')
                            ->badge(),
                        TextColumn::make('paid_on')
                            ->label('Paid On')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->placeholder('—'),
                        TextColumn::make('created_on')
                            ->label('Created')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->since()
                            ->icon('heroicon-o-clock'),
                        IconColumn::make('is_outgoing')
                            ->label('Outgoing')
                            ->boolean()
                            ->trueColor('info')
                            ->falseColor('success'),
                    ])->carded(), // @phpstan-ignore method.notFound
                ])->glow(), // @phpstan-ignore method.notFound
            ])
            ->filters([
                SelectFilter::make('payment_type')
                    ->label('Type')
                    ->options([
                        'purchase' => 'Purchase',
                        'refund' => 'Refund',
                        'payout' => 'Payout',
                    ]),
                SelectFilter::make('currency')
                    ->label('Currency')
                    ->options(function (): array {
                        $query = Payment::query();

                        if (method_exists($query->getModel(), 'scopeForOwner')) {
                            $query->forOwner();
                        }

                        return $query
                            ->select('currency')
                            ->distinct()
                            ->orderBy('currency')
                            ->pluck('currency', 'currency')
                            ->filter()
                            ->all();
                    }),
                Filter::make('is_outgoing')
                    ->label('Outgoing Only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_outgoing', true)),
                Filter::make('paid')
                    ->label('Paid Transactions')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('paid_on')),
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
        return PaymentInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'payments';
    }
}
