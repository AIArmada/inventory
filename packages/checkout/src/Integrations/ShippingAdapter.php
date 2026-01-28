<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Integrations;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Jnt\Shipping\JntShippingDriver;
use AIArmada\Shipping\Actions\CalculateShippingRate;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Support\Collection;

final class ShippingAdapter
{
    private bool $hasJnt = false;

    public function __construct()
    {
        $this->hasJnt = class_exists(JntShippingDriver::class)
            && config('checkout.integrations.shipping.jnt.enabled', true);
    }

    /**
     * Get available shipping rates for the checkout session.
     *
     * @return array<array<string, mixed>>
     */
    public function getRates(CheckoutSession $session): array
    {
        if (! class_exists(ShippingManager::class) || ! class_exists(CalculateShippingRate::class)) {
            return $this->getDefaultRates($session);
        }

        $shippingData = $session->shipping_data ?? [];
        $cartSnapshot = $session->cart_snapshot ?? [];

        $origin = $this->getOriginAddress();
        $destination = $this->buildDestinationAddress($shippingData);
        $packages = $this->buildPackages($cartSnapshot);

        if ($origin === null || $destination === null || $packages === []) {
            return $this->getDefaultRates($session);
        }

        /** @var Collection<int, RateQuoteData> $rateQuotes */
        $rateQuotes = app(CalculateShippingRate::class)->handle(
            origin: $origin,
            destination: $destination,
            packages: $packages,
            carrier: null,
            options: [
                'currency' => $session->currency,
            ],
        );

        $rates = $this->normalizeRates($rateQuotes);

        if (! $this->hasJnt || ! config('checkout.integrations.shipping.jnt.auto_detect', true)) {
            $rates = array_values(array_filter($rates, fn (array $rate): bool => ($rate['carrier'] ?? '') !== 'jnt'));
        }

        return $rates;
    }

    public function hasJntIntegration(): bool
    {
        return $this->hasJnt;
    }

    /**
     * Get JNT-specific shipping data.
     *
     * @param  array<string, mixed>  $selectedRate
     * @return array<string, mixed>
     */
    public function getJntShippingData(CheckoutSession $session, array $selectedRate): array
    {
        if (! $this->hasJnt) {
            return [];
        }

        $shippingData = $session->shipping_data ?? [];

        return [
            'service_type' => $selectedRate['service_type'] ?? 'EZ',
            'estimated_delivery' => $selectedRate['estimated_delivery'] ?? null,
            'tracking_available' => true,
            'origin' => $this->getOriginData(),
            'destination' => [
                'name' => $shippingData['name'] ?? '',
                'phone' => $shippingData['phone'] ?? '',
                'address' => $this->formatAddress($shippingData),
                'postcode' => $shippingData['postcode'] ?? '',
                'city' => $shippingData['city'] ?? '',
                'state' => $shippingData['state'] ?? '',
                'country' => $shippingData['country'] ?? 'MY',
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getDefaultRates(CheckoutSession $session): array
    {
        /** @var int $defaultRate */
        $defaultRate = config('checkout.defaults.shipping_rate', 1000);

        return [
            [
                'method_id' => 'flat_rate',
                'carrier' => 'Standard',
                'name' => 'Standard Shipping',
                'rate' => $defaultRate,
                'currency' => $session->currency,
                'estimated_days' => '3-5',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $shippingData
     */
    private function buildDestinationAddress(array $shippingData): ?AddressData
    {
        $line1 = $shippingData['line1'] ?? $shippingData['address_line_1'] ?? '';
        $postcode = $shippingData['postcode'] ?? '';

        if ($line1 === '' || $postcode === '') {
            return null;
        }

        return AddressData::from([
            'name' => $shippingData['name'] ?? 'Customer',
            'phone' => $shippingData['phone'] ?? '',
            'line1' => $line1,
            'line2' => $shippingData['line2'] ?? $shippingData['address_line_2'] ?? null,
            'city' => $shippingData['city'] ?? null,
            'state' => $shippingData['state'] ?? null,
            'postcode' => $postcode,
            'country' => $shippingData['country'] ?? 'MY',
            'email' => $shippingData['email'] ?? null,
            'company' => $shippingData['company'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cartSnapshot
     * @return array<PackageData>
     */
    private function buildPackages(array $cartSnapshot): array
    {
        $items = $cartSnapshot['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $item): ?PackageData {
            $attributes = $item['attributes'] ?? [];
            $quantity = (int) ($item['quantity'] ?? 1);

            $weight = (int) ($item['weight'] ?? $attributes['weight'] ?? 0);
            $weight = $weight * max(1, $quantity);

            if ($weight <= 0) {
                return null;
            }

            $dimensions = $item['dimensions'] ?? $attributes['dimensions'] ?? null;

            return PackageData::from([
                'weight' => $weight,
                'length' => $dimensions['length'] ?? null,
                'width' => $dimensions['width'] ?? null,
                'height' => $dimensions['height'] ?? null,
                'declaredValue' => $item['price'] ?? null,
                'quantity' => 1,
            ]);
        }, $items)));
    }

    private function getOriginAddress(): ?AddressData
    {
        $origin = config('shipping.defaults.origin', []);

        $line1 = $origin['line1'] ?? $origin['address'] ?? '';
        $postcode = $origin['postcode'] ?? $origin['post_code'] ?? '';

        if ($line1 === '' || $postcode === '') {
            return null;
        }

        return AddressData::from([
            'name' => $origin['name'] ?? config('app.name', 'Store'),
            'phone' => $origin['phone'] ?? '',
            'line1' => $line1,
            'line2' => $origin['line2'] ?? $origin['address2'] ?? null,
            'city' => $origin['city'] ?? null,
            'state' => $origin['state'] ?? null,
            'postcode' => $postcode,
            'country' => $origin['country'] ?? $origin['country_code'] ?? 'MY',
            'company' => $origin['company'] ?? null,
            'email' => $origin['email'] ?? null,
        ]);
    }

    /**
     * @param  Collection<int, RateQuoteData>  $rates
     * @return array<array<string, mixed>>
     */
    private function normalizeRates(Collection $rates): array
    {
        return $rates->map(function (RateQuoteData $rate): array {
            return [
                'method_id' => $rate->carrier . '_' . $rate->service,
                'carrier' => $rate->carrier,
                'service' => $rate->service,
                'service_type' => $rate->service,
                'name' => $rate->serviceDescription ?? $rate->service,
                'rate' => $rate->rate,
                'currency' => $rate->currency,
                'estimated_days' => $rate->estimatedDays,
                'estimated_delivery' => $rate->estimatedDeliveryDate,
                'quote_id' => $rate->quoteId,
                'calculated_locally' => $rate->calculatedLocally,
                'restrictions' => $rate->restrictions,
                'note' => $rate->note,
                'expires_at' => $rate->expiresAt?->format(DATE_ATOM),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function getOriginData(): array
    {
        return [
            'name' => config('jnt.origin.name', config('shipping.defaults.origin.name', '')),
            'phone' => config('jnt.origin.phone', config('shipping.defaults.origin.phone', '')),
            'line1' => config('jnt.origin.line1', config('shipping.defaults.origin.line1', config('shipping.defaults.origin.address', ''))),
            'line2' => config('jnt.origin.line2', config('shipping.defaults.origin.line2', null)),
            'postcode' => config('jnt.origin.postcode', config('shipping.defaults.origin.postcode', '')),
            'city' => config('jnt.origin.city', config('shipping.defaults.origin.city', '')),
            'state' => config('jnt.origin.state', config('shipping.defaults.origin.state', '')),
            'country' => config('jnt.origin.country', config('shipping.defaults.origin.country', 'MY')),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['line1'] ?? $address['address_line_1'] ?? '',
            $address['line2'] ?? $address['address_line_2'] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
