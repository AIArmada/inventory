<?php

declare(strict_types=1);

namespace AIArmada\Chip\Health;

use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Health check for CHIP payment gateway.
 */
class ChipGatewayCheck extends CommerceHealthCheck
{
    public ?string $name = 'CHIP Payment Gateway';

    /**
     * The API endpoint to check.
     */
    protected string $endpoint = 'https://gate.chip-in.asia/api/v1/';

    /**
     * Timeout in seconds.
     */
    protected int $timeout = 10;

    /**
     * Set the endpoint to check.
     */
    public function endpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Set the timeout.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Perform the health check.
     */
    protected function performCheck(): Result
    {
        $brandId = config('chip.collect.brand_id');
        $apiKey = config('chip.collect.api_key');

        if (empty($brandId) || empty($apiKey)) {
            return $this->warning('CHIP credentials not configured');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($apiKey)
                ->get($this->endpoint . 'brands/' . $brandId);

            if ($response->successful()) {
                return $this->success('CHIP gateway is operational', [
                    'brand_id' => $brandId,
                    'response_time_ms' => $response->handlerStats()['total_time'] ?? null,
                ]);
            }

            return $this->failure("CHIP API returned status {$response->status()}", [
                'status_code' => $response->status(),
            ]);
        } catch (Throwable $e) {
            return $this->failure('Failed to connect to CHIP gateway: ' . $e->getMessage());
        }
    }
}
