<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Completed;

describe('GenerateInvoice Action', function (): void {
    describe('Invoice Generation', function (): void {
        it('can be instantiated', function (): void {
            $action = new GenerateInvoice();
            expect($action)->toBeInstanceOf(GenerateInvoice::class);
        });

        it('can save invoice to path', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-INV1-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $action = new GenerateInvoice();
            $path = storage_path('app/test-invoice.pdf');

            // Mock the PDF facade to avoid actual file generation
            $mockBuilder = Mockery::mock(\Spatie\LaravelPdf\PdfBuilder::class);
            $mockBuilder->shouldReceive('format')->andReturnSelf();
            $mockBuilder->shouldReceive('margins')->andReturnSelf();
            $mockBuilder->shouldReceive('name')->andReturnSelf();
            $mockBuilder->shouldReceive('save')->with($path)->andReturnSelf();

            \Spatie\LaravelPdf\Facades\Pdf::shouldReceive('view')->andReturn($mockBuilder);

            $result = $action->save($order, $path);

            expect($result)->toBe($path);
        });

        it('has download method', function (): void {
            $action = new GenerateInvoice();
            expect(method_exists($action, 'download'))->toBeTrue();
        });
    });
});