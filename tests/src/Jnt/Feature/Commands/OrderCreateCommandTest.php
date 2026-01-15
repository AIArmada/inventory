<?php

declare(strict_types=1);

use AIArmada\Jnt\Console\Commands\OrderCreateCommand;
use Illuminate\Support\Facades\Http;

test('order create command creates order with command-line options', function (): void {
    Http::fake([
        '*/addOrder' => Http::response([
            'code' => '1',
            'msg' => 'success',
            'data' => [
                'txlogisticId' => 'TEST123',
                'billCode' => 'JT123456789MY',
            ],
        ], 200),
    ]);

    $this->artisan(OrderCreateCommand::class, [
        '--order-id' => 'TEST123',
        '--sender-name' => 'John Doe',
        '--sender-mobile' => '0123456789',
        '--receiver-name' => 'Jane Smith',
        '--receiver-mobile' => '0198765432',
        '--receiver-address' => '123 Main St, KL',
        '--item-name' => 'Test Item',
        '--item-qty' => 1,
        '--weight' => 1.5,
    ])
        ->expectsOutput('✓ Order created successfully!')
        ->assertExitCode(0);
});

test('order create command handles API errors', function (): void {
    Http::fake([
        '*/addOrder' => Http::response([
            'code' => '0',
            'msg' => 'Invalid request',
        ], 400),
    ]);

    $this->artisan(OrderCreateCommand::class, [
        '--order-id' => 'TEST123',
        '--sender-name' => 'John Doe',
        '--sender-mobile' => '0123456789',
        '--receiver-name' => 'Jane Smith',
        '--receiver-mobile' => '0198765432',
        '--receiver-address' => '123 Main St, KL',
        '--item-name' => 'Test Item',
    ])
        ->assertExitCode(1);
});
