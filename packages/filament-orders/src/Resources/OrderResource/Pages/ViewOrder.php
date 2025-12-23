<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Shipped;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('confirm_payment')
                ->label('Confirm Payment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Payment')
                ->modalDescription('Are you sure you want to mark this order as paid?')
                ->authorize(function (Order $record): bool {
                    $user = Filament::auth()->user();

                    return $user ? Gate::forUser($user)->allows('update', $record) : false;
                })
                ->form([
                    \Filament\Forms\Components\TextInput::make('transaction_id')
                        ->label('Transaction ID')
                        ->required(),
                    \Filament\Forms\Components\Select::make('gateway')
                        ->label('Payment Gateway')
                        ->options([
                            'stripe' => 'Stripe',
                            'chip' => 'CHIP',
                            'manual' => 'Manual',
                        ])
                        ->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    try {
                        $service = app(OrderService::class);
                        $service->confirmPayment(
                            $record,
                            $data['transaction_id'],
                            $data['gateway'],
                            $record->grand_total,
                        );

                        Notification::make()
                            ->title('Payment confirmed')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'paid_at']);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Unable to confirm payment')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (Order $record) => $record->status instanceof PendingPayment),

            Actions\Action::make('ship_order')
                ->label('Ship Order')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Create Shipment')
                ->authorize(function (Order $record): bool {
                    $user = Filament::auth()->user();

                    return $user ? Gate::forUser($user)->allows('update', $record) : false;
                })
                ->form([
                    \Filament\Forms\Components\TextInput::make('carrier')
                        ->label('Carrier')
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    try {
                        $service = app(OrderService::class);
                        $service->ship(
                            $record,
                            $data['carrier'],
                            $data['tracking_number'],
                        );

                        Notification::make()
                            ->title('Order shipped')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'shipped_at']);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Unable to ship order')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (Order $record) => $record->status instanceof Processing),

            Actions\Action::make('confirm_delivery')
                ->label('Confirm Delivery')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(function (Order $record): bool {
                    $user = Filament::auth()->user();

                    return $user ? Gate::forUser($user)->allows('update', $record) : false;
                })
                ->action(function (Order $record): void {
                    try {
                        $service = app(OrderService::class);
                        $service->confirmDelivery($record);

                        Notification::make()
                            ->title('Delivery confirmed')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'delivered_at']);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Unable to confirm delivery')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (Order $record) => $record->status instanceof Shipped),

            Actions\Action::make('cancel_order')
                ->label('Cancel Order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Order')
                ->modalDescription('Are you sure you want to cancel this order? This action cannot be undone.')
                ->authorize(function (Order $record): bool {
                    $user = Filament::auth()->user();

                    return $user ? Gate::forUser($user)->allows('cancel', $record) : false;
                })
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Cancellation Reason')
                        ->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    try {
                        $service = app(OrderService::class);

                        $canceledBy = Filament::auth()->id();

                        $service->cancel(
                            $record,
                            $data['reason'],
                            $canceledBy ? (string) $canceledBy : null,
                        );

                        Notification::make()
                            ->title('Order canceled')
                            ->warning()
                            ->send();

                        $this->refreshFormData(['status', 'canceled_at', 'cancellation_reason']);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Unable to cancel order')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn (Order $record) => $record->canBeCanceled()),

            Actions\Action::make('download_invoice')
                ->label('Download Invoice')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (Order $record) => route('filament-orders.invoice.download', $record))
                ->openUrlInNewTab()
                ->authorize(function (Order $record): bool {
                    $user = Filament::auth()->user();

                    return $user ? Gate::forUser($user)->allows('view', $record) : false;
                })
                ->visible(fn (Order $record) => $record->isPaid()),
        ];
    }
}
