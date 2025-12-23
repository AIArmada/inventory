<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources;

use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Pages;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\InvoiceStatus;
use AIArmada\FilamentCashier\Support\UnifiedInvoice;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UnifiedInvoiceResource extends Resource
{
    protected static ?string $model = null;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 20;

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-cashier.resources.navigation_sort.invoices', 20);
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::invoices.title');
    }

    public static function getModelLabel(): string
    {
        return __('filament-cashier::invoices.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cashier::invoices.plural');
    }

    public static function table(Table $table): Table
    {
        $gatewayDetector = app(GatewayDetector::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label(__('filament-cashier::invoices.table.number'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('userId')
                    ->label(__('filament-cashier::invoices.table.customer'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('gateway')
                    ->label(__('filament-cashier::invoices.table.gateway'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $gatewayDetector->getLabel($state))
                    ->color(fn (string $state): string => $gatewayDetector->getColor($state))
                    ->icon(fn (string $state): string => $gatewayDetector->getIcon($state)),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('filament-cashier::invoices.table.status'))
                    ->badge()
                    ->formatStateUsing(fn (InvoiceStatus $state): string => $state->label())
                    ->color(fn (InvoiceStatus $state): string => $state->color())
                    ->icon(fn (InvoiceStatus $state): string => $state->icon()),

                Tables\Columns\TextColumn::make('formattedAmount')
                    ->label(__('filament-cashier::invoices.table.amount'))
                    ->getStateUsing(fn (UnifiedInvoice $record): string => $record->formattedAmount()),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('filament-cashier::invoices.table.date'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('paidAt')
                    ->label(__('filament-cashier::invoices.table.paid_at'))
                    ->date(config('filament-cashier.tables.date_format', 'M d, Y'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label(__('filament-cashier::invoices.table.gateway'))
                    ->options($gatewayDetector->getGatewayOptions()),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('filament-cashier::invoices.table.status'))
                    ->options(
                        collect(InvoiceStatus::cases())
                            ->mapWithKeys(fn (InvoiceStatus $status) => [$status->value => $status->label()])
                            ->toArray()
                    ),
            ])
            ->actions([
                Action::make('download')
                    ->label(__('filament-cashier::invoices.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (UnifiedInvoice $record): ?string => $record->pdfUrl)
                    ->openUrlInNewTab()
                    ->visible(fn (UnifiedInvoice $record): bool => $record->pdfUrl !== null),

                Action::make('view_external')
                    ->label(fn (UnifiedInvoice $record): string => __('filament-cashier::invoices.actions.view_external', [
                        'gateway' => $record->gatewayConfig()['label'],
                    ]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (UnifiedInvoice $record): string => $record->externalDashboardUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export')
                        ->label(__('filament-cashier::subscriptions.bulk.export'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records): StreamedResponse {
                            return response()->streamDownload(function () use ($records): void {
                                $output = fopen('php://output', 'w');
                                fputcsv($output, ['Invoice #', 'Gateway', 'Amount', 'Status', 'Date', 'Paid At']);

                                foreach ($records as $invoice) {
                                    fputcsv($output, [
                                        $invoice->number,
                                        $invoice->gateway,
                                        $invoice->formattedAmount(),
                                        $invoice->status->value,
                                        $invoice->date->format('Y-m-d'),
                                        $invoice->paidAt?->format('Y-m-d') ?? '',
                                    ]);
                                }

                                fclose($output);
                            }, 'invoices-' . now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->poll(config('filament-cashier.tables.polling_interval', '45s'))
            ->emptyStateHeading(__('filament-cashier::invoices.empty.title'))
            ->emptyStateDescription(__('filament-cashier::invoices.empty.description'))
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
        ];
    }

    public static function resolveRecordRouteBinding(int | string $key, ?Closure $modifyQuery = null): ?Model
    {
        return null;
    }
}
