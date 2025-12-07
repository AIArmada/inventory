<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Shipping;

use AIArmada\Jnt\Data\AddressData as JntAddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Enums\TrackingStatus as JntTrackingStatus;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentResultData;
use AIArmada\Shipping\Data\ShippingMethodData;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\TrackingStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * J&T Express shipping driver implementation.
 *
 * Integrates J&T Express with the unified shipping abstraction layer.
 */
class JntShippingDriver implements ShippingDriverInterface
{
    public function __construct(
        protected readonly JntExpressService $jntService,
        protected readonly JntTrackingService $trackingService,
        protected readonly JntStatusMapper $statusMapper,
    ) {}

    public function getCarrierCode(): string
    {
        return 'jnt';
    }

    public function getCarrierName(): string
    {
        return 'J&T Express';
    }

    public function supports(DriverCapability $capability): bool
    {
        return match ($capability) {
            DriverCapability::RateQuotes => true,
            DriverCapability::LabelGeneration => true,
            DriverCapability::Tracking => true,
            DriverCapability::Webhooks => true,
            DriverCapability::CashOnDelivery => true,
            DriverCapability::BatchOperations => true,
            DriverCapability::Returns => false,
            DriverCapability::AddressValidation => false,
            DriverCapability::PickupScheduling => true,
            DriverCapability::InsuranceClaims => false,
            DriverCapability::MultiPackage => false,
            DriverCapability::InternationalShipping => false,
        };
    }

    public function getAvailableMethods(): Collection
    {
        return collect([
            new ShippingMethodData(
                code: 'EZ',
                name: 'J&T EZ',
                description: 'Standard delivery',
                minDays: 2,
                maxDays: 4,
                trackingAvailable: true,
            ),
            new ShippingMethodData(
                code: 'EXPRESS',
                name: 'J&T Express',
                description: 'Express delivery',
                minDays: 1,
                maxDays: 2,
                trackingAvailable: true,
            ),
        ]);
    }

    /**
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    public function getRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        $totalWeight = array_sum(array_map(fn (PackageData $p) => $p->weight, $packages));

        // Calculate rate based on weight
        $rate = $this->calculateWeightBasedRate($totalWeight, $destination);
        $estimatedDays = $this->getEstimatedDays($destination);

        return collect([
            new RateQuoteData(
                carrier: 'jnt',
                service: 'EZ',
                rate: $rate,
                currency: 'MYR',
                estimatedDays: $estimatedDays,
                serviceDescription: 'J&T EZ - Standard Delivery',
                calculatedLocally: true,
            ),
        ]);
    }

    public function createShipment(ShipmentData $data): ShipmentResultData
    {
        try {
            $sender = $this->convertToJntAddress($data->origin, 'sender');
            $receiver = $this->convertToJntAddress($data->destination, 'receiver');
            $items = $this->convertToJntItems($data->items);
            $packageInfo = $this->createPackageInfo($data);

            $orderData = $this->jntService->createOrder(
                sender: $sender,
                receiver: $receiver,
                items: $items,
                packageInfo: $packageInfo,
                orderId: $data->reference,
                additionalData: [
                    'cod_amount' => $data->isCashOnDelivery() ? ($data->codAmount / 100) : 0,
                    'remark' => $data->instructions ?? '',
                ],
            );

            $trackingNumber = $orderData->billCode ?? null;

            // Get label URL
            $labelUrl = null;
            if ($trackingNumber !== null) {
                try {
                    $printResponse = $this->jntService->printOrder($data->reference, $trackingNumber);
                    $labelUrl = $printResponse['data']['url'] ?? null;
                } catch (Throwable) {
                    // Label generation may fail, continue without it
                }
            }

            return new ShipmentResultData(
                success: true,
                trackingNumber: $trackingNumber,
                carrierReference: $orderData->txlogisticId,
                labelUrl: $labelUrl,
                rawResponse: $orderData->toArray(),
            );
        } catch (Throwable $e) {
            return new ShipmentResultData(
                success: false,
                error: $e->getMessage(),
            );
        }
    }

    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            $response = $this->jntService->cancelOrder(
                orderId: $trackingNumber,
                reason: CancellationReason::CustomerRequest
            );

            return isset($response['success']) && $response['success'];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateLabel(string $trackingNumber, array $options = []): LabelData
    {
        $response = $this->jntService->printOrder(
            orderId: $trackingNumber,
            templateName: $options['template'] ?? null
        );

        $labelUrl = $response['data']['url'] ?? null;
        $labelContent = $response['data']['content'] ?? null;

        return new LabelData(
            format: 'pdf',
            url: $labelUrl,
            content: $labelContent,
            trackingNumber: $trackingNumber,
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        $response = $this->trackingService->track(trackingNumber: $trackingNumber);

        $events = collect($response['events'] ?? [])
            ->map(function (array $event) {
                /** @var JntTrackingStatus $jntStatus */
                $jntStatus = $event['status'];
                $normalizedStatus = $this->mapJntStatusToNormalized($jntStatus);

                /** @var Carbon $occurredAt */
                $occurredAt = $event['occurred_at'];

                return new TrackingEventData(
                    code: $jntStatus->value,
                    description: $event['description'] ?? '',
                    timestamp: $occurredAt->toDateTimeImmutable(),
                    normalizedStatus: $normalizedStatus,
                    location: $event['location'] ?? null,
                    raw: $event,
                );
            });

        /** @var JntTrackingStatus $currentJntStatus */
        $currentJntStatus = $response['current_status'];
        $currentStatus = $this->mapJntStatusToNormalized($currentJntStatus);
        $latestEvent = $events->first();

        return new TrackingData(
            trackingNumber: $trackingNumber,
            status: $currentStatus,
            events: $events,
            carrier: 'jnt',
            deliveredAt: $currentStatus === TrackingStatus::Delivered
            ? $latestEvent?->timestamp
            : null,
        );
    }

    public function validateAddress(AddressData $address): AddressValidationResult
    {
        // JNT doesn't provide address validation API
        return new AddressValidationResult(
            valid: true,
            warnings: ['Address validation not available for J&T Express.'],
        );
    }

    public function servicesDestination(AddressData $destination): bool
    {
        // JNT services Malaysia
        return in_array(mb_strtoupper($destination->countryCode), ['MY', 'MYS'], true);
    }

    /**
     * Convert shipping address data to JNT format.
     */
    protected function convertToJntAddress(AddressData $address, string $type): JntAddressData
    {
        return new JntAddressData(
            name: $address->name,
            mobile: $address->phone,
            phone: $address->phone,
            city: $address->city ?? '',
            area: $address->state ?? '',
            detailAddress: $address->address,
            postcode: $address->postCode,
        );
    }

    /**
     * Convert shipment items to JNT format.
     *
     * @param  array<\AIArmada\Shipping\Data\ShipmentItemData>  $items
     * @return array<ItemData>
     */
    protected function convertToJntItems(array $items): array
    {
        return array_map(function ($item) {
            return new ItemData(
                itemName: $item->name,
                quantity: $item->quantity,
                itemValue: ($item->declaredValue ?? 0) / 100,
            );
        }, $items);
    }

    /**
     * Create package info from shipment data.
     */
    protected function createPackageInfo(ShipmentData $data): PackageInfoData
    {
        return new PackageInfoData(
            weight: round($data->getTotalWeight() / 1000, 2),
            goodsValue: ($data->declaredValue ?? 0) / 100,
        );
    }

    /**
     * Map JNT tracking status to normalized shipping status.
     */
    protected function mapJntStatusToNormalized(JntTrackingStatus $jntStatus): TrackingStatus
    {
        return match ($jntStatus) {
            JntTrackingStatus::Pending => TrackingStatus::Pending,
            JntTrackingStatus::PickedUp => TrackingStatus::PickedUp,
            JntTrackingStatus::InTransit => TrackingStatus::InTransit,
            JntTrackingStatus::AtHub => TrackingStatus::AtFacility,
            JntTrackingStatus::OutForDelivery => TrackingStatus::OutForDelivery,
            JntTrackingStatus::DeliveryAttempted => TrackingStatus::DeliveryAttempt,
            JntTrackingStatus::Delivered => TrackingStatus::Delivered,
            JntTrackingStatus::ReturnInitiated => TrackingStatus::ReturnToSender,
            JntTrackingStatus::Returned => TrackingStatus::Returned,
            JntTrackingStatus::Exception => TrackingStatus::Exception,
        };
    }

    /**
     * Calculate weight-based shipping rate.
     */
    protected function calculateWeightBasedRate(int $weightGrams, AddressData $destination): int
    {
        $weightKg = max(1, ceil($weightGrams / 1000));

        // Base rate configuration
        $baseRate = config('jnt.shipping.base_rate', 600); // RM6.00
        $perKgRate = config('jnt.shipping.per_kg_rate', 200); // RM2.00 per additional kg
        $regionMultiplier = $this->getRegionMultiplier($destination);

        $rate = $baseRate + (max(0, $weightKg - 1) * $perKgRate);

        return (int) round($rate * $regionMultiplier);
    }

    /**
     * Get region multiplier for pricing.
     */
    protected function getRegionMultiplier(AddressData $destination): float
    {
        $postcode = $destination->postCode;

        // East Malaysia (Sabah/Sarawak) - higher rates
        $eastMalaysiaRanges = [
            ['87000', '91999'], // Sabah
            ['93000', '98999'], // Sarawak
        ];

        foreach ($eastMalaysiaRanges as $range) {
            if ($postcode >= $range[0] && $postcode <= $range[1]) {
                return config('jnt.shipping.east_malaysia_multiplier', 1.5);
            }
        }

        return 1.0;
    }

    /**
     * Get estimated delivery days.
     */
    protected function getEstimatedDays(AddressData $destination): int
    {
        $postcode = $destination->postCode;

        // East Malaysia takes longer
        $eastMalaysiaRanges = [
            ['87000', '91999'],
            ['93000', '98999'],
        ];

        foreach ($eastMalaysiaRanges as $range) {
            if ($postcode >= $range[0] && $postcode <= $range[1]) {
                return config('jnt.shipping.east_malaysia_days', 5);
            }
        }

        return config('jnt.shipping.standard_days', 3);
    }
}
