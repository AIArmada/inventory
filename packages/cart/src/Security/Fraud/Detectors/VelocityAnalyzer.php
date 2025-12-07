<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud\Detectors;

use AIArmada\Cart\Security\Fraud\DetectorResult;
use AIArmada\Cart\Security\Fraud\FraudContext;
use AIArmada\Cart\Security\Fraud\FraudDetectorInterface;
use AIArmada\Cart\Security\Fraud\FraudSignal;
use Illuminate\Support\Facades\Cache;

/**
 * Analyzes request velocity to detect automated or abusive behavior.
 *
 * Monitors patterns like:
 * - Too many cart operations in short time
 * - Multiple carts from same IP
 * - Rapid checkout attempts
 * - Bot-like behavior patterns
 */
final class VelocityAnalyzer implements FraudDetectorInterface
{
    private const NAME = 'velocity_analyzer';

    private const CACHE_PREFIX = 'fraud:velocity:';

    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    public function __construct()
    {
        $this->configuration = config('cart.fraud.detectors.velocity', [
            'enabled' => true,
            'weight' => 1.2,
            'max_cart_operations_per_minute' => 30,
            'max_cart_operations_per_hour' => 200,
            'max_carts_per_ip_per_hour' => 10,
            'max_checkout_attempts_per_hour' => 5,
            'suspicious_item_add_interval_ms' => 100,
            'max_items_per_second' => 5,
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function isEnabled(): bool
    {
        return $this->configuration['enabled'] ?? true;
    }

    public function getWeight(): float
    {
        return $this->configuration['weight'] ?? 1.2;
    }

    public function detect(FraudContext $context): DetectorResult
    {
        $startTime = microtime(true);
        $signals = [];

        $signals = array_merge($signals, $this->checkOperationVelocity($context));
        $signals = array_merge($signals, $this->checkIpVelocity($context));
        $signals = array_merge($signals, $this->checkUserVelocity($context));
        $signals = array_merge($signals, $this->checkItemAddPattern($context));
        $signals = array_merge($signals, $this->checkCheckoutAttempts($context));

        $this->recordActivity($context);

        $executionTime = (int) ((microtime(true) - $startTime) * 1000);

        return DetectorResult::withSignals(
            self::NAME,
            $signals,
            $executionTime
        );
    }

    /**
     * Record a failed checkout for IP.
     */
    public function recordFailedCheckout(string $ipAddress): void
    {
        $this->incrementCounter("ip:{$ipAddress}:failed_checkouts", 3600);
    }

    /**
     * Record a checkout attempt for user.
     */
    public function recordCheckoutAttempt(string $userId): void
    {
        $this->incrementCounter("user:{$userId}:checkouts", 3600);
    }

    /**
     * Check overall operation velocity.
     *
     * @return array<FraudSignal>
     */
    private function checkOperationVelocity(FraudContext $context): array
    {
        $signals = [];
        $sessionId = $context->sessionId;

        if (! $sessionId) {
            return $signals;
        }

        $opsPerMinute = $this->getOperationCount($sessionId, 60);
        $maxPerMinute = $this->configuration['max_cart_operations_per_minute'] ?? 30;

        if ($opsPerMinute > $maxPerMinute) {
            $signals[] = FraudSignal::high(
                'excessive_velocity',
                self::NAME,
                "Session has {$opsPerMinute} cart operations in last minute (limit: {$maxPerMinute})",
                'Rate limit or block this session - possible bot activity',
                ['operations_per_minute' => $opsPerMinute, 'limit' => $maxPerMinute]
            );
        } elseif ($opsPerMinute > ($maxPerMinute * 0.7)) {
            $signals[] = FraudSignal::medium(
                'high_velocity',
                self::NAME,
                "Session approaching rate limit: {$opsPerMinute} operations in last minute",
                'Monitor session for continued high activity',
                ['operations_per_minute' => $opsPerMinute, 'limit' => $maxPerMinute]
            );
        }

        $opsPerHour = $this->getOperationCount($sessionId, 3600);
        $maxPerHour = $this->configuration['max_cart_operations_per_hour'] ?? 200;

        if ($opsPerHour > $maxPerHour) {
            $signals[] = FraudSignal::medium(
                'sustained_high_velocity',
                self::NAME,
                "Session has {$opsPerHour} cart operations in last hour (limit: {$maxPerHour})",
                'Consider requiring CAPTCHA or verification',
                ['operations_per_hour' => $opsPerHour, 'limit' => $maxPerHour]
            );
        }

        return $signals;
    }

    /**
     * Check IP-based velocity.
     *
     * @return array<FraudSignal>
     */
    private function checkIpVelocity(FraudContext $context): array
    {
        $signals = [];
        $ipAddress = $context->ipAddress;

        if (! $ipAddress) {
            return $signals;
        }

        $cartsPerHour = $this->getUniqueCartsForIp($ipAddress, 3600);
        $maxCartsPerHour = $this->configuration['max_carts_per_ip_per_hour'] ?? 10;

        if ($cartsPerHour > $maxCartsPerHour) {
            $signals[] = FraudSignal::high(
                'multiple_carts_from_ip',
                self::NAME,
                "IP {$ipAddress} has created {$cartsPerHour} carts in last hour (limit: {$maxCartsPerHour})",
                'Possible cart stuffing or denial of service attack',
                ['ip_address' => $ipAddress, 'carts_per_hour' => $cartsPerHour, 'limit' => $maxCartsPerHour]
            );
        } elseif ($cartsPerHour > ($maxCartsPerHour * 0.5)) {
            $signals[] = FraudSignal::low(
                'elevated_ip_activity',
                self::NAME,
                "IP {$ipAddress} has {$cartsPerHour} carts in last hour",
                'May be shared IP (office, university) - monitor for other signals',
                ['ip_address' => $ipAddress, 'carts_per_hour' => $cartsPerHour]
            );
        }

        return $signals;
    }

    /**
     * Check user-based velocity.
     *
     * @return array<FraudSignal>
     */
    private function checkUserVelocity(FraudContext $context): array
    {
        $signals = [];
        $userId = $context->userId;

        if (! $userId) {
            return $signals;
        }

        $recentCarts = $this->getRecentCartsForUser($userId, 3600);
        $checkoutAttempts = $this->getCheckoutAttempts($userId, 3600);

        if ($recentCarts > 5) {
            $signals[] = FraudSignal::medium(
                'multiple_user_carts',
                self::NAME,
                "User created {$recentCarts} carts in last hour",
                'May indicate cart abandonment farming or testing',
                ['user_id' => $userId, 'cart_count' => $recentCarts]
            );
        }

        $maxCheckouts = $this->configuration['max_checkout_attempts_per_hour'] ?? 5;
        if ($checkoutAttempts > $maxCheckouts) {
            $signals[] = FraudSignal::high(
                'excessive_checkout_attempts',
                self::NAME,
                "User has {$checkoutAttempts} checkout attempts in last hour (limit: {$maxCheckouts})",
                'Possible payment fraud or card testing',
                ['user_id' => $userId, 'attempts' => $checkoutAttempts, 'limit' => $maxCheckouts]
            );
        }

        return $signals;
    }

    /**
     * Check item add patterns for bot-like behavior.
     *
     * @return array<FraudSignal>
     */
    private function checkItemAddPattern(FraudContext $context): array
    {
        $signals = [];
        $cartId = $context->getCartId();
        $timestamps = $this->getItemAddTimestamps($cartId);

        if (count($timestamps) < 3) {
            return $signals;
        }

        $suspiciousIntervalMs = $this->configuration['suspicious_item_add_interval_ms'] ?? 100;
        $suspiciousCount = 0;

        for ($i = 1; $i < count($timestamps); $i++) {
            $interval = ($timestamps[$i] - $timestamps[$i - 1]) * 1000;
            if ($interval < $suspiciousIntervalMs) {
                $suspiciousCount++;
            }
        }

        $suspiciousRatio = $suspiciousCount / (count($timestamps) - 1);

        if ($suspiciousRatio > 0.5) {
            $signals[] = FraudSignal::high(
                'bot_like_pattern',
                self::NAME,
                sprintf('%.0f%% of item adds had suspiciously fast intervals (<%dms)', $suspiciousRatio * 100, $suspiciousIntervalMs),
                'Likely automated script - block and investigate',
                [
                    'suspicious_ratio' => $suspiciousRatio,
                    'suspicious_count' => $suspiciousCount,
                    'total_intervals' => count($timestamps) - 1,
                ]
            );
        } elseif ($suspiciousRatio > 0.2) {
            $signals[] = FraudSignal::medium(
                'possible_automation',
                self::NAME,
                sprintf('%.0f%% of item adds were unusually fast', $suspiciousRatio * 100),
                'May be automated - require CAPTCHA on checkout',
                ['suspicious_ratio' => $suspiciousRatio]
            );
        }

        $maxItemsPerSecond = $this->configuration['max_items_per_second'] ?? 5;
        $itemsInLastSecond = $this->countItemsAddedInWindow($timestamps, 1);

        if ($itemsInLastSecond > $maxItemsPerSecond) {
            $signals[] = FraudSignal::high(
                'rapid_item_addition',
                self::NAME,
                "{$itemsInLastSecond} items added in last second (limit: {$maxItemsPerSecond})",
                'Extremely fast activity - likely automated',
                ['items_per_second' => $itemsInLastSecond, 'limit' => $maxItemsPerSecond]
            );
        }

        return $signals;
    }

    /**
     * Check recent checkout attempts.
     *
     * @return array<FraudSignal>
     */
    private function checkCheckoutAttempts(FraudContext $context): array
    {
        $signals = [];
        $ipAddress = $context->ipAddress;

        if (! $ipAddress) {
            return $signals;
        }

        $failedAttempts = $this->getFailedCheckoutsForIp($ipAddress, 3600);

        if ($failedAttempts > 10) {
            $signals[] = FraudSignal::high(
                'excessive_failed_checkouts',
                self::NAME,
                "IP has {$failedAttempts} failed checkout attempts in last hour",
                'Possible card testing - block IP temporarily',
                ['ip_address' => $ipAddress, 'failed_attempts' => $failedAttempts]
            );
        } elseif ($failedAttempts > 3) {
            $signals[] = FraudSignal::medium(
                'multiple_failed_checkouts',
                self::NAME,
                "IP has {$failedAttempts} failed checkout attempts",
                'Monitor for continued failures',
                ['ip_address' => $ipAddress, 'failed_attempts' => $failedAttempts]
            );
        }

        return $signals;
    }

    /**
     * Record activity for tracking.
     */
    private function recordActivity(FraudContext $context): void
    {
        $now = now()->timestamp;

        if ($context->sessionId) {
            $this->incrementCounter("session:{$context->sessionId}:ops", 3600);
        }

        if ($context->ipAddress) {
            $this->recordCartForIp($context->ipAddress, $context->getCartId());
        }

        if ($context->userId) {
            $this->recordCartForUser($context->userId, $context->getCartId());
        }

        $this->recordItemAddTimestamp($context->getCartId(), $now);
    }

    /**
     * Get operation count for session.
     */
    private function getOperationCount(string $sessionId, int $windowSeconds): int
    {
        $key = self::CACHE_PREFIX."session:{$sessionId}:ops";

        return (int) Cache::get($key, 0);
    }

    /**
     * Get unique carts for IP.
     */
    private function getUniqueCartsForIp(string $ipAddress, int $windowSeconds): int
    {
        $key = self::CACHE_PREFIX."ip:{$ipAddress}:carts";
        $carts = Cache::get($key, []);

        $cutoff = now()->timestamp - $windowSeconds;

        return count(array_filter($carts, fn ($timestamp) => $timestamp > $cutoff));
    }

    /**
     * Get recent carts for user.
     */
    private function getRecentCartsForUser(string $userId, int $windowSeconds): int
    {
        $key = self::CACHE_PREFIX."user:{$userId}:carts";
        $carts = Cache::get($key, []);

        $cutoff = now()->timestamp - $windowSeconds;

        return count(array_filter($carts, fn ($timestamp) => $timestamp > $cutoff));
    }

    /**
     * Get checkout attempts for user.
     */
    private function getCheckoutAttempts(string $userId, int $windowSeconds): int
    {
        $key = self::CACHE_PREFIX."user:{$userId}:checkouts";

        return (int) Cache::get($key, 0);
    }

    /**
     * Get failed checkouts for IP.
     */
    private function getFailedCheckoutsForIp(string $ipAddress, int $windowSeconds): int
    {
        $key = self::CACHE_PREFIX."ip:{$ipAddress}:failed_checkouts";

        return (int) Cache::get($key, 0);
    }

    /**
     * Get item add timestamps for cart.
     *
     * @return array<float>
     */
    private function getItemAddTimestamps(string $cartId): array
    {
        $key = self::CACHE_PREFIX."cart:{$cartId}:item_timestamps";

        return Cache::get($key, []);
    }

    /**
     * Count items added in time window.
     *
     * @param  array<float>  $timestamps
     */
    private function countItemsAddedInWindow(array $timestamps, int $windowSeconds): int
    {
        $cutoff = microtime(true) - $windowSeconds;

        return count(array_filter($timestamps, fn ($ts) => $ts > $cutoff));
    }

    /**
     * Increment a counter with TTL.
     */
    private function incrementCounter(string $key, int $ttl): void
    {
        $fullKey = self::CACHE_PREFIX.$key;
        $current = (int) Cache::get($fullKey, 0);
        Cache::put($fullKey, $current + 1, $ttl);
    }

    /**
     * Record cart for IP tracking.
     */
    private function recordCartForIp(string $ipAddress, string $cartId): void
    {
        $key = self::CACHE_PREFIX."ip:{$ipAddress}:carts";
        $carts = Cache::get($key, []);

        if (! isset($carts[$cartId])) {
            $carts[$cartId] = now()->timestamp;
            Cache::put($key, $carts, 3600);
        }
    }

    /**
     * Record cart for user tracking.
     */
    private function recordCartForUser(string $userId, string $cartId): void
    {
        $key = self::CACHE_PREFIX."user:{$userId}:carts";
        $carts = Cache::get($key, []);

        if (! isset($carts[$cartId])) {
            $carts[$cartId] = now()->timestamp;
            Cache::put($key, $carts, 3600);
        }
    }

    /**
     * Record item add timestamp.
     */
    private function recordItemAddTimestamp(string $cartId, float $timestamp): void
    {
        $key = self::CACHE_PREFIX."cart:{$cartId}:item_timestamps";
        $timestamps = Cache::get($key, []);
        $timestamps[] = $timestamp;

        $timestamps = array_slice($timestamps, -100);

        Cache::put($key, $timestamps, 3600);
    }
}
