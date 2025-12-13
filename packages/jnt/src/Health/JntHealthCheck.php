<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Health;

use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use AIArmada\Jnt\Http\JntClient;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Health check for J&T Express API.
 */
class JntHealthCheck extends CommerceHealthCheck
{
    public ?string $name = 'J&T Express API';

    /**
     * Timeout in seconds.
     */
    protected int $timeout = 15;

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
        $eccompanyid = config('jnt.eccompanyid');
        $apiAccount = config('jnt.api_account');
        $privateKey = config('jnt.private_key');
        $baseUrl = config('jnt.base_url');

        if (empty($eccompanyid) || empty($apiAccount)) {
            return $this->warning('J&T credentials not configured');
        }

        if (empty($privateKey) || empty($baseUrl)) {
            return $this->warning('J&T API configuration incomplete');
        }

        try {
            // Verify the client can be instantiated
            $client = app(JntClient::class);

            // If we get here, the client is configured
            return $this->success('J&T Express API is configured', [
                'eccompanyid' => $eccompanyid,
                'base_url' => $baseUrl,
            ]);
        } catch (Throwable $e) {
            return $this->failure('Failed to initialize J&T Express client: ' . $e->getMessage());
        }
    }
}
