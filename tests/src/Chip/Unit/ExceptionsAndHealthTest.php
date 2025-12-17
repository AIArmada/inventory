<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\NoRecurringTokenException;
use AIArmada\Chip\Health\ChipGatewayCheck;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Result;

describe('NoRecurringTokenException', function (): void {
    it('can be constructed with default message', function (): void {
        $exception = new NoRecurringTokenException;

        expect($exception)->toBeInstanceOf(NoRecurringTokenException::class)
            ->and($exception->getMessage())->toBe('No recurring token available');
    });

    it('can be constructed with custom message', function (): void {
        $exception = new NoRecurringTokenException('Custom error message');

        expect($exception->getMessage())->toBe('Custom error message');
    });

    it('is throwable', function (): void {
        expect(fn () => throw new NoRecurringTokenException('Test'))
            ->toThrow(NoRecurringTokenException::class, 'Test');
    });
});

describe('ChipGatewayCheck', function (): void {
    it('can be instantiated', function (): void {
        $check = new ChipGatewayCheck;

        expect($check)->toBeInstanceOf(ChipGatewayCheck::class)
            ->and($check->name)->toBe('CHIP Payment Gateway');
    });

    it('can set endpoint', function (): void {
        $check = new ChipGatewayCheck;
        $result = $check->endpoint('https://custom-endpoint.com/');

        expect($result)->toBe($check); // Returns self for chaining
    });

    it('can set timeout', function (): void {
        $check = new ChipGatewayCheck;
        $result = $check->timeout(30);

        expect($result)->toBe($check); // Returns self for chaining
    });

    it('returns warning when credentials not configured', function (): void {
        config(['chip.collect.brand_id' => null, 'chip.collect.api_key' => null]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('warning');
    });

    it('returns warning when only brand_id is missing', function (): void {
        config(['chip.collect.brand_id' => null, 'chip.collect.api_key' => 'test-key']);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('warning');
    });

    it('returns warning when only api_key is missing', function (): void {
        config(['chip.collect.brand_id' => 'test-brand', 'chip.collect.api_key' => null]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('warning');
    });

    it('returns success when API responds successfully', function (): void {
        config([
            'chip.collect.brand_id' => 'test-brand-123',
            'chip.collect.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '*' => Http::response(['id' => 'test-brand-123', 'name' => 'Test Brand'], 200),
        ]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('ok');
    });

    it('returns failure when API responds with error', function (): void {
        config([
            'chip.collect.brand_id' => 'test-brand-123',
            'chip.collect.api_key' => 'test-api-key',
        ]);

        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('failed');
    });

    it('returns failure when connection fails', function (): void {
        config([
            'chip.collect.brand_id' => 'test-brand-123',
            'chip.collect.api_key' => 'test-api-key',
        ]);

        Http::fake(function (): void {
            throw new Exception('Connection refused');
        });

        $check = new ChipGatewayCheck;
        $result = $check->run();

        expect($result)->toBeInstanceOf(Result::class)
            ->and($result->status->value)->toBe('failed');
    });
});
