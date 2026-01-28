<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Services;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;
use Throwable;

/**
 * Bridge service for integrating with the Cart package.
 *
 * Provides methods to create shipments from cart/order data and
 * generate deep links to orders in the Filament admin panel.
 */
class CartBridge
{
    /**
     * Create shipment data from cart/order data.
     *
     * @param  array{
     *     id: string,
     *     reference?: string,
     *     shipping_address: array{
     *         name: string,
     *         phone: string,
     *         line1: string,
     *         line2?: string,
     *         city?: string,
     *         state?: string,
     *         postcode: string,
     *         country?: string,
     *     },
     *     billing_address?: array{
     *         name: string,
     *         phone: string,
     *         line1: string,
     *         line2?: string,
     *         city?: string,
     *         state?: string,
     *         postcode: string,
     *         country?: string,
     *     },
     *     items: array<int, array{
     *         name: string,
     *         sku?: string,
     *         quantity: int,
     *         weight?: int,
     *         declared_value?: int,
     *     }>,
     *     carrier_code?: string,
     *     service_code?: string,
     *     declared_value?: int,
     *     instructions?: string,
     *     cod_amount?: int,
     * }  $orderData
     */
    public function createShipmentDataFromOrder(array $orderData): ShipmentData
    {
        $originAddress = $this->getDefaultOriginAddress();
        $destinationAddress = $this->parseAddress($orderData['shipping_address']);
        $items = $this->parseItems($orderData['items']);

        return new ShipmentData(
            reference: $orderData['reference'] ?? $orderData['id'],
            carrierCode: $orderData['carrier_code'] ?? 'manual',
            serviceCode: $orderData['service_code'] ?? 'standard',
            origin: $originAddress,
            destination: $destinationAddress,
            items: $items,
            declaredValue: $orderData['declared_value'] ?? $this->calculateDeclaredValue($items),
            instructions: $orderData['instructions'] ?? null,
            codAmount: $orderData['cod_amount'] ?? null,
        );
    }

    /**
     * Get the URL to view an order in the Filament panel.
     */
    public function getOrderUrl(string $orderId): ?string
    {
        // Check if the Orders resource exists
        $resourceClass = 'AIArmada\\FilamentOrders\\Resources\\OrderResource';

        if (! class_exists($resourceClass)) {
            return null;
        }

        try {
            return $resourceClass::getUrl('view', ['record' => $orderId]);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get the URL to create a shipment from an order.
     */
    public function getCreateShipmentUrl(string $orderId): string
    {
        return route('filament.admin.resources.shipments.create', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Check if the Cart package is installed.
     */
    public function isCartPackageInstalled(): bool
    {
        return class_exists('AIArmada\\Cart\\Cart');
    }

    /**
     * Get default origin address from configuration.
     */
    protected function getDefaultOriginAddress(): AddressData
    {
        $config = config('shipping.defaults.origin', []);

        return new AddressData(
            name: $config['name'] ?? config('app.name', 'Warehouse'),
            phone: $config['phone'] ?? '',
            line1: $config['line1'] ?? $config['address'] ?? '',
            line2: $config['line2'] ?? $config['address2'] ?? null,
            city: $config['city'] ?? null,
            state: $config['state'] ?? null,
            postcode: $config['postcode'] ?? $config['post_code'] ?? '',
            country: $config['country'] ?? $config['country_code'] ?? 'MY',
        );
    }

    /**
     * Parse address array to AddressData.
     *
     * @param  array{
     *     name: string,
     *     phone: string,
     *     line1: string,
     *     line2?: string,
     *     city?: string,
     *     state?: string,
     *     postcode: string,
     *     country?: string,
     * }  $address
     */
    protected function parseAddress(array $address): AddressData
    {
        return new AddressData(
            name: $address['name'],
            phone: $address['phone'],
            line1: $address['line1'] ?? $address['address'] ?? $address['address1'] ?? '',
            line2: $address['line2'] ?? $address['address2'] ?? null,
            city: $address['city'] ?? null,
            state: $address['state'] ?? null,
            postcode: $address['postcode'] ?? $address['post_code'] ?? '',
            country: $address['country'] ?? $address['country_code'] ?? 'MY',
        );
    }

    /**
     * Parse items array to ShipmentItemData array.
     *
     * @param  array<int, array{
     *     name: string,
     *     sku?: string,
     *     quantity: int,
     *     weight?: int,
     *     declared_value?: int,
     * }>  $items
     * @return array<ShipmentItemData>
     */
    protected function parseItems(array $items): array
    {
        return array_map(function ($item) {
            return new ShipmentItemData(
                name: $item['name'],
                sku: $item['sku'] ?? null,
                quantity: $item['quantity'],
                weight: $item['weight'] ?? null,
                declaredValue: $item['declared_value'] ?? null,
            );
        }, $items);
    }

    /**
     * Calculate total declared value from items.
     *
     * @param  array<ShipmentItemData>  $items
     */
    protected function calculateDeclaredValue(array $items): int
    {
        return array_reduce($items, function (int $total, ShipmentItemData $item) {
            return $total + (($item->declaredValue ?? 0) * $item->quantity);
        }, 0);
    }
}
