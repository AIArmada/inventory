<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Contracts\FulfillmentHandler;
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
                        ->options((array) config('filament-orders.payment_gateways', [
                            'stripe' => 'Stripe',
                            'chip' => 'CHIP',
                            'manual' => 'Manual',
                        ]))
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
                ->form(function (): array {
                    // Check if FulfillmentHandler (shipping integration) is available
                    $hasFulfillmentHandler = app()->bound(FulfillmentHandler::class);
                    $carriers = $this->getAvailableCarriers();

                    if ($hasFulfillmentHandler && $carriers !== []) {
                        // Shipping integration available - show carrier selection
                        return [
                            \Filament\Forms\Components\Select::make('carrier')
                                ->label('Shipping Carrier')
                                ->options($carriers)
                                ->default(config('shipping.default', 'jnt'))
                                ->required()
                                ->helperText('Shipment will be created automatically via carrier API'),
                            \Filament\Forms\Components\Select::make('service')
                                ->label('Service Type')
                                ->options([
                                    'standard' => 'Standard Delivery',
                                    'express' => 'Express Delivery',
                                    'EZ' => 'J&T EZ',
                                ])
                                ->default('standard'),
                        ];
                    }

                    // Fallback to manual input
                    return [
                        \Filament\Forms\Components\TextInput::make('carrier')
                            ->label('Carrier')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required(),
                    ];
                })
                ->action(function (Order $record, array $data): void {
                    try {
                        $service = app(OrderService::class);

                        // Check if FulfillmentHandler is available for API-based shipping
                        if (app()->bound(FulfillmentHandler::class) && ! isset($data['tracking_number'])) {
                            /** @var FulfillmentHandler $fulfillmentHandler */
                            $fulfillmentHandler = app(FulfillmentHandler::class);

                            $result = $fulfillmentHandler->createShipment($record, [
                                'carrier' => $data['carrier'] ?? config('shipping.default', 'jnt'),
                                'service' => $data['service'] ?? 'standard',
                            ]);

                            if (! $result['success']) {
                                throw new \RuntimeException($result['error'] ?? 'Failed to create shipment via carrier API');
                            }

                            // Update order with tracking info from API
                            $service->ship(
                                $record,
                                $this->getCarrierName($data['carrier'] ?? 'jnt'),
                                $result['tracking_number'] ?? 'PENDING',
                            );

                            Notification::make()
                                ->title('Order shipped via ' . $this->getCarrierName($data['carrier'] ?? 'jnt'))
                                ->body('Tracking number: ' . ($result['tracking_number'] ?? 'Pending'))
                                ->success()
                                ->send();
                        } else {
                            // Manual tracking input
                            $service->ship(
                                $record,
                                $data['carrier'],
                                $data['tracking_number'],
                            );

                            Notification::make()
                                ->title('Order shipped')
                                ->success()
                                ->send();
                        }

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

    /**
     * Get available shipping carriers.
     *
     * @return array<string, string>
     */
    protected function getAvailableCarriers(): array
    {
        $carriers = [];

        // Check for JNT integration
        if (class_exists(\AIArmada\Jnt\Shipping\JntShippingDriver::class)) {
            $carriers['jnt'] = 'J&T Express';
        }

        // Check for manual shipping driver
        if ($carriers === []) {
            $carriers['manual'] = 'Manual Shipping';
        }

        return $carriers;
    }

    /**
     * Get carrier display name.
     */
    protected function getCarrierName(string $carrierCode): string
    {
        return match ($carrierCode) {
            'jnt' => 'J&T Express',
            'manual' => 'Manual',
            default => ucfirst($carrierCode),
        };
    }
}
