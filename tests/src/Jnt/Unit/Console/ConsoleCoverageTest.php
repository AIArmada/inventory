<?php

declare(strict_types=1);

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\WebhookService;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelData\DataCollection;

describe('OrderCancelCommand', function (): void {
    it('cancels order with provided reason and confirmation', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('cancelOrder')
            ->once()
            ->with('ORDER123', CancellationReason::CUSTOMER_REQUEST)
            ->andReturn(['success' => true]);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:cancel', [
            'order-id' => 'ORDER123',
            '--reason' => 'customer_request',
        ])
            ->expectsConfirmation('Cancel order ORDER123?', 'yes')
            ->assertExitCode(0);
    });

    it('cancels order with an explicit tracking number', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('cancelOrder')
            ->once()
            ->with('ORDER123', CancellationReason::CUSTOMER_REQUEST, 'JNTTRACK123')
            ->andReturn(['success' => true]);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:cancel', [
            'order-id' => 'ORDER123',
            '--reason' => 'customer_request',
            '--tracking-number' => 'JNTTRACK123',
        ])
            ->expectsConfirmation('Cancel order ORDER123?', 'yes')
            ->assertExitCode(0);
    });

    it('cancels order by selecting reason interactively', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('cancelOrder')
            ->once()
            ->with('ORDER123', CancellationReason::OUT_OF_STOCK)
            ->andReturn(['success' => true]);

        $this->instance(JntExpressService::class, $jntService);

        $choices = collect(CancellationReason::cases())
            ->map(fn (CancellationReason $reason): string => $reason->value)
            ->all();

        $this->artisan('jnt:order:cancel', ['order-id' => 'ORDER123'])
            ->expectsChoice('Select cancellation reason', 'out_of_stock', $choices)
            ->expectsConfirmation('Cancel order ORDER123?', 'yes')
            ->assertExitCode(0);
    });

    it('aborts cancellation when not confirmed', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldNotReceive('cancelOrder');

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:cancel', [
            'order-id' => 'ORDER123',
            '--reason' => 'customer_request',
        ])
            ->expectsConfirmation('Cancel order ORDER123?', 'no')
            ->assertExitCode(0);
    });

    it('handles API errors gracefully', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('cancelOrder')
            ->andThrow(new JntApiException('API Error', null, []));

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:cancel', [
            'order-id' => 'ORDER123',
            '--reason' => 'customer_request',
        ])
            ->expectsConfirmation('Cancel order ORDER123?', 'yes')
            ->assertExitCode(1);
    });
});

describe('OrderPrintCommand', function (): void {
    it('prints waybill successfully with base64 content', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('printOrder')
            ->once()
            ->with('ORDER123')
            ->andReturn(['urlContent' => 'https://example.com/waybill.pdf']);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:print', ['order-id' => 'ORDER123'])
            ->assertExitCode(0);
    });

    it('prints waybill with an explicit tracking number', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('printOrder')
            ->once()
            ->with('ORDER123', 'JNTTRACK123')
            ->andReturn(['urlContent' => 'https://example.com/waybill.pdf']);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:print', [
            'order-id' => 'ORDER123',
            '--tracking-number' => 'JNTTRACK123',
        ])
            ->assertExitCode(0);
    });

    it('handles API errors gracefully', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('printOrder')
            ->andThrow(new JntApiException('API Error', null, []));

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:print', ['order-id' => 'ORDER123'])
            ->assertExitCode(1);
    });
});

describe('OrderTrackCommand', function (): void {
    it('displays tracking information when found', function (): void {
        $detail = new TrackingDetailData(
            scanTypeCode: '100',
            scanTime: '2024-01-15 10:00:00',
            description: 'Delivered',
            scanTypeName: 'Delivered',
            scanType: 'SIGN',
        );
        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([$detail], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: 'ORDER123',
            details: $details,
        );

        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('trackParcel')
            ->once()
            ->with('ORDER123')
            ->andReturn($trackingData);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:track', ['order-id' => 'ORDER123'])
            ->assertExitCode(0);
    });

    it('tracks by tracking number when the flag is provided', function (): void {
        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNTTRACK123',
            orderId: null,
            details: $details,
        );

        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('trackParcel')
            ->once()
            ->with(null, 'JNTTRACK123')
            ->andReturn($trackingData);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:track', [
            'order-id' => 'JNTTRACK123',
            '--tracking-number' => true,
        ])
            ->assertExitCode(0);
    });

    it('warns when no tracking information found', function (): void {
        /** @var DataCollection<int, TrackingDetailData> $details */
        $details = TrackingDetailData::collect([], DataCollection::class);

        $trackingData = new TrackingData(
            trackingNumber: 'JNT123456',
            orderId: 'ORDER123',
            details: $details,
        );

        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('trackParcel')
            ->once()
            ->with('ORDER123')
            ->andReturn($trackingData);

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:track', ['order-id' => 'ORDER123'])
            ->assertExitCode(0);
    });

    it('handles API errors gracefully', function (): void {
        $jntService = Mockery::mock(JntExpressService::class);
        $jntService->shouldReceive('trackParcel')
            ->andThrow(new JntApiException('API Error', null, []));

        $this->instance(JntExpressService::class, $jntService);

        $this->artisan('jnt:order:track', ['order-id' => 'ORDER123'])
            ->assertExitCode(1);
    });
});

describe('WebhookTestCommand', function (): void {
    it('sends test webhook successfully', function (): void {
        Http::fake([
            '*' => Http::response('OK', 200),
        ]);

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('generateSignature')
            ->andReturn('dummy_signature');

        $this->instance(WebhookService::class, $webhookService);

        $this->artisan('jnt:webhook:test', ['--url' => 'http://test.com/webhook'])
            ->expectsOutputToContain('Status: 200')
            ->doesntExpectOutputToContain('Response: OK')
            ->doesntExpectOutputToContain('Response: ')
            ->assertExitCode(0);
    });

    it('handles failed webhook response', function (): void {
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('generateSignature')
            ->andReturn('dummy_signature');

        $this->instance(WebhookService::class, $webhookService);

        $this->artisan('jnt:webhook:test', ['--url' => 'http://test.com/webhook'])
            ->expectsOutputToContain('Status: 500')
            ->doesntExpectOutputToContain('Response: Error')
            ->doesntExpectOutputToContain('Response: ')
            ->assertExitCode(1);
    });
});
