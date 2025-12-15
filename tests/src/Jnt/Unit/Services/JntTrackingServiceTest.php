<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use Spatie\LaravelData\DataCollection;

/**
 * Helper function to create a TrackingDetailData object with all required fields
 *
 * @param  array<string, mixed>  $overrides
 */
function createTrackingDetail(array $overrides = []): TrackingDetailData
{
    $defaults = [
        'scanTime' => '2024-01-15 10:00:00',
        'description' => 'Test description',
        'scanTypeCode' => '100',
        'scanTypeName' => 'Test Type',
        'scanType' => 'SIGN',
    ];

    return new TrackingDetailData(
        scanTime: $overrides['scanTime'] ?? $defaults['scanTime'],
        description: $overrides['description'] ?? $defaults['description'],
        scanTypeCode: $overrides['scanTypeCode'] ?? $defaults['scanTypeCode'],
        scanTypeName: $overrides['scanTypeName'] ?? $defaults['scanTypeName'],
        scanType: $overrides['scanType'] ?? $defaults['scanType'],
        actualWeight: $overrides['actualWeight'] ?? null,
        scanNetworkTypeName: $overrides['scanNetworkTypeName'] ?? null,
        scanNetworkName: $overrides['scanNetworkName'] ?? null,
        staffName: $overrides['staffName'] ?? null,
        staffContact: $overrides['staffContact'] ?? null,
        scanNetworkContact: $overrides['scanNetworkContact'] ?? null,
        scanNetworkProvince: $overrides['scanNetworkProvince'] ?? null,
        scanNetworkCity: $overrides['scanNetworkCity'] ?? null,
        scanNetworkArea: $overrides['scanNetworkArea'] ?? null,
        signaturePictureUrl: $overrides['signaturePictureUrl'] ?? null,
        longitude: $overrides['longitude'] ?? null,
        latitude: $overrides['latitude'] ?? null,
        timeZone: $overrides['timeZone'] ?? null,
        scanNetworkCountry: $overrides['scanNetworkCountry'] ?? null,
    );
}

describe('JntTrackingService', function (): void {
    it('can be instantiated', function (): void {
        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);

        $service = new JntTrackingService($expressService, $statusMapper);

        expect($service)->toBeInstanceOf(JntTrackingService::class);
    });

    it('gets normalized status from tracking detail', function (): void {
        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);
        $statusMapper->shouldReceive('fromCode')
            ->with('100')
            ->andReturn(TrackingStatus::Delivered);

        $service = new JntTrackingService($expressService, $statusMapper);

        $detail = createTrackingDetail([
            'scanTypeCode' => '100',
            'description' => 'Delivered',
        ]);

        $status = $service->getNormalizedStatus($detail);

        expect($status)->toBe(TrackingStatus::Delivered);
    });

    it('returns Pending status when no details exist', function (): void {
        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);

        $service = new JntTrackingService($expressService, $statusMapper);

        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: 'ORDER123',
            details: $details,
        );

        $status = $service->getCurrentStatus($trackingData);

        expect($status)->toBe(TrackingStatus::Pending);
    });

    it('gets current status from first detail', function (): void {
        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);
        $statusMapper->shouldReceive('fromCode')
            ->with('100')
            ->andReturn(TrackingStatus::Delivered);

        $service = new JntTrackingService($expressService, $statusMapper);

        $detail = createTrackingDetail([
            'scanTypeCode' => '100',
            'description' => 'Delivered',
        ]);

        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([$detail], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: 'ORDER123',
            details: $details,
        );

        $status = $service->getCurrentStatus($trackingData);

        expect($status)->toBe(TrackingStatus::Delivered);
    });

    it('tracks parcel via express service', function (): void {
        $detail = createTrackingDetail([
            'scanTypeCode' => '10',
            'scanTypeName' => 'Pickup',
            'scanTime' => '2024-01-15 10:00:00',
            'description' => 'Parcel picked up',
            'scanNetworkCity' => 'Kuala Lumpur',
            'scanNetworkProvince' => 'Wilayah Persekutuan',
        ]);

        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([$detail], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: 'ORDER123',
            details: $details,
        );

        $expressService = Mockery::mock(JntExpressService::class);
        $expressService->shouldReceive('trackParcel')
            ->with('ORDER123', null)
            ->andReturn($trackingData);

        $statusMapper = Mockery::mock(JntStatusMapper::class);
        $statusMapper->shouldReceive('fromCode')
            ->with('10')
            ->andReturn(TrackingStatus::PickedUp);

        $service = new JntTrackingService($expressService, $statusMapper);

        $result = $service->track('ORDER123');

        expect($result)->toBeArray();
        expect($result['tracking_number'])->toBe('JNT123456');
        expect($result['order_id'])->toBe('ORDER123');
        expect($result['current_status'])->toBe(TrackingStatus::PickedUp);
        expect($result['events'])->toHaveCount(1);
    });

    it('parses tracking data into normalized format', function (): void {
        $detail = createTrackingDetail([
            'scanTypeCode' => '30',
            'scanTypeName' => 'Arrival',
            'scanTime' => '2024-01-15 14:00:00',
            'description' => 'Arrived at hub',
            'scanNetworkName' => 'Central Hub',
        ]);

        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([$detail], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT789',
            orderId: null,
            details: $details,
        );

        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);
        $statusMapper->shouldReceive('fromCode')
            ->with('30')
            ->andReturn(TrackingStatus::AtHub);

        $service = new JntTrackingService($expressService, $statusMapper);

        $result = $service->parseTrackingData($trackingData);

        expect($result['tracking_number'])->toBe('JNT789');
        expect($result['order_id'])->toBeNull();
        expect($result['current_status'])->toBe(TrackingStatus::AtHub);
        expect($result['events'])->toBeArray();
        expect($result['events'][0]['description'])->toBe('Arrived at hub');
    });

    it('formats location from tracking detail', function (): void {
        $detail = createTrackingDetail([
            'scanTypeCode' => '30',
            'scanTime' => '2024-01-15 14:00:00',
            'description' => 'Arrived',
            'scanNetworkArea' => 'Area A',
            'scanNetworkCity' => 'City B',
            'scanNetworkProvince' => 'Province C',
            'scanNetworkCountry' => 'MY',
        ]);

        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([$detail], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123',
            orderId: null,
            details: $details,
        );

        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);
        $statusMapper->shouldReceive('fromCode')
            ->andReturn(TrackingStatus::AtHub);

        $service = new JntTrackingService($expressService, $statusMapper);

        $result = $service->parseTrackingData($trackingData);

        expect($result['events'][0]['location'])->toBe('Area A, City B, Province C, MY');
    });

    it('uses network name when location parts are empty', function (): void {
        $detail = createTrackingDetail([
            'scanTypeCode' => '30',
            'scanTime' => '2024-01-15 14:00:00',
            'description' => 'Arrived',
            'scanNetworkName' => 'Main Hub KL',
        ]);

        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([$detail], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123',
            orderId: null,
            details: $details,
        );

        $expressService = Mockery::mock(JntExpressService::class);
        $statusMapper = Mockery::mock(JntStatusMapper::class);
        $statusMapper->shouldReceive('fromCode')
            ->andReturn(TrackingStatus::AtHub);

        $service = new JntTrackingService($expressService, $statusMapper);

        $result = $service->parseTrackingData($trackingData);

        expect($result['events'][0]['location'])->toBe('Main Hub KL');
    });

    it('batches sync tracking for multiple orders', function (): void {
        $expressService = Mockery::mock(JntExpressService::class);
        $expressService->shouldReceive('trackParcel')
            ->andThrow(new Exception('API Error'));

        $statusMapper = Mockery::mock(JntStatusMapper::class);

        $service = new JntTrackingService($expressService, $statusMapper);

        $order1 = new JntOrder;
        $order1->forceFill(['id' => 'test-id', 'tracking_number' => 'JNT001']);

        $result = $service->batchSyncTracking([$order1]);

        expect($result)->toHaveKey('successful');
        expect($result)->toHaveKey('failed');
        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0]['error'])->toBe('API Error');
    });
});
