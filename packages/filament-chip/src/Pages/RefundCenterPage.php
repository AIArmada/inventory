<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Pages;

use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Services\ChipCollectService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Action as TableAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Throwable;

class RefundCenterPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?string $navigationLabel = 'Refund Center';

    protected static ?string $title = 'Refund Center';

    protected static ?string $slug = 'chip/refunds';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-chip.navigation.group', 'Payments');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                tap(Purchase::query(), function ($query): void {
                    if (method_exists($query->getModel(), 'scopeForOwner')) {
                        $query->forOwner();
                    }
                })
                    ->whereIn('status', ['paid', 'partially_refunded'])
                    ->where('is_test', false)
                    ->latest('created_on')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client_id')
                    ->label('Client')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('purchase.total')
                    ->label('Amount')
                    ->formatStateUsing(function ($state): string {
                        if (is_array($state)) {
                            $amount = (int) ($state['amount'] ?? 0);
                        } else {
                            $amount = (int) $state;
                        }

                        return 'RM ' . number_format($amount / 100, 2);
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partially_refunded' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_on')
                    ->label('Created')
                    ->formatStateUsing(fn ($record): string => $record->createdOn?->format('Y-m-d H:i') ?? 'N/A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'partially_refunded' => 'Partially Refunded',
                    ]),

                SelectFilter::make('payment_method')
                    ->options(
                        tap(Purchase::query(), function ($query): void {
                            if (method_exists($query->getModel(), 'scopeForOwner')) {
                                $query->forOwner();
                            }
                        })
                            ->whereNotNull('payment_method')
                            ->distinct()
                            ->pluck('payment_method', 'payment_method')
                            ->toArray()
                    ),
            ])
            ->actions([
                TableAction::make('full_refund')
                    ->label('Full Refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Process Full Refund')
                    ->modalDescription(fn (Purchase $record): string => sprintf(
                        'Are you sure you want to issue a full refund for purchase %s? This cannot be undone.',
                        $record->reference ?? $record->id
                    ))
                    ->action(function (Purchase $record): void {
                        $service = app(ChipCollectService::class);

                        try {
                            $service->refundPurchase((string) $record->id);
                            Notification::make()
                                ->title('Refund processed successfully')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Refund failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Purchase $record): bool => $record->status === 'paid'),

                TableAction::make('partial_refund')
                    ->label('Partial Refund')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->form([
                        Section::make('Refund Details')
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Refund Amount (MYR)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->prefix('RM')
                                    ->helperText('Enter the amount to refund in MYR.'),
                            ]),
                    ])
                    ->action(function (Purchase $record, array $data): void {
                        $service = app(ChipCollectService::class);
                        $amountInCents = (int) ($data['amount'] * 100);

                        try {
                            $service->refundPurchase((string) $record->id, $amountInCents);
                            Notification::make()
                                ->title('Partial refund processed successfully')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Refund failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (Purchase $record): bool => in_array($record->status, ['paid', 'partially_refunded'], true)),

                TableAction::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Purchase $record): string => route('filament.admin.resources.purchases.view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No refundable purchases')
            ->emptyStateDescription('Paid purchases eligible for refund will appear here.')
            ->emptyStateIcon('heroicon-o-receipt-refund')
            ->poll('30s');
    }

    public function render(): View
    {
        return view('filament-chip::pages.refund-center');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_all_purchases')
                ->label('All Purchases')
                ->icon(Heroicon::QueueList)
                ->color('info')
                ->url(fn (): string => route('filament.admin.resources.purchases.index')),
        ];
    }
}
