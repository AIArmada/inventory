<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects and stores fraud signals for analysis and reporting.
 *
 * Signals are stored in cache for real-time analysis and optionally
 * persisted to database for long-term tracking and ML training.
 */
final class FraudSignalCollector
{
    private const CACHE_PREFIX = 'fraud:signals:';

    private const CACHE_TTL = 86400; // 24 hours

    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    public function __construct()
    {
        $this->configuration = config('cart.fraud.collector', [
            'persist_to_database' => false,
            'table' => 'cart_fraud_signals',
            'retention_days' => 90,
        ]);
    }

    /**
     * Collect fraud signals from an analysis.
     */
    public function collect(FraudContext $context, Collection $signals): void
    {
        $key = $this->getCacheKey($context);
        $data = [
            'context' => $context->toArray(),
            'signals' => $signals->map(fn (FraudSignal $s) => $s->toArray())->toArray(),
            'collected_at' => now()->toIso8601String(),
        ];

        Cache::put($key, $data, self::CACHE_TTL);

        $this->recordForUser($context->userId, $signals);
        $this->recordForIp($context->ipAddress, $signals);
        $this->recordForSession($context->sessionId, $signals);

        if ($this->shouldPersist()) {
            $this->persistToDatabase($context, $signals);
        }

        if ($signals->isNotEmpty()) {
            $this->logSignals($context, $signals);
        }
    }

    /**
     * Get recent signals for a user.
     *
     * @return array<array<string, mixed>>
     */
    public function getRecentSignalsForUser(?string $userId, int $limit = 100): array
    {
        if ($userId === null) {
            return [];
        }

        $key = "fraud:user:{$userId}:signals";
        $data = Cache::get($key, []);

        return array_slice($data, 0, $limit);
    }

    /**
     * Get recent signals for an IP address.
     *
     * @return array<array<string, mixed>>
     */
    public function getRecentSignalsForIp(?string $ipAddress, int $limit = 100): array
    {
        if ($ipAddress === null) {
            return [];
        }

        $key = "fraud:ip:{$ipAddress}:signals";
        $data = Cache::get($key, []);

        return array_slice($data, 0, $limit);
    }

    /**
     * Get signal count for user in time window.
     */
    public function getSignalCountForUser(?string $userId, int $windowMinutes = 60): int
    {
        if ($userId === null) {
            return 0;
        }

        $signals = $this->getRecentSignalsForUser($userId);
        $cutoff = now()->subMinutes($windowMinutes);

        return count(array_filter($signals, function ($signal) use ($cutoff) {
            $timestamp = $signal['timestamp'] ?? null;
            if (! $timestamp) {
                return false;
            }

            return strtotime($timestamp) >= $cutoff->timestamp;
        }));
    }

    /**
     * Get signal count for IP in time window.
     */
    public function getSignalCountForIp(?string $ipAddress, int $windowMinutes = 60): int
    {
        if ($ipAddress === null) {
            return 0;
        }

        $signals = $this->getRecentSignalsForIp($ipAddress);
        $cutoff = now()->subMinutes($windowMinutes);

        return count(array_filter($signals, function ($signal) use ($cutoff) {
            $timestamp = $signal['timestamp'] ?? null;
            if (! $timestamp) {
                return false;
            }

            return strtotime($timestamp) >= $cutoff->timestamp;
        }));
    }

    /**
     * Get aggregated risk score for user.
     */
    public function getAggregatedScoreForUser(?string $userId, int $windowMinutes = 60): int
    {
        if ($userId === null) {
            return 0;
        }

        $signals = $this->getRecentSignalsForUser($userId);
        $cutoff = now()->subMinutes($windowMinutes);
        $totalScore = 0;

        foreach ($signals as $signal) {
            $timestamp = $signal['timestamp'] ?? null;
            if ($timestamp && strtotime($timestamp) >= $cutoff->timestamp) {
                $totalScore += $signal['score'] ?? 0;
            }
        }

        return min(100, $totalScore);
    }

    /**
     * Check if user has been flagged for fraud.
     */
    public function isUserFlagged(?string $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return Cache::get("fraud:user:{$userId}:flagged", false);
    }

    /**
     * Flag a user for fraud review.
     */
    public function flagUser(?string $userId, string $reason): void
    {
        if ($userId === null) {
            return;
        }

        Cache::put("fraud:user:{$userId}:flagged", true, now()->addDays(7));
        Cache::put("fraud:user:{$userId}:flag_reason", $reason, now()->addDays(7));

        Log::warning('User flagged for fraud review', [
            'user_id' => $userId,
            'reason' => $reason,
        ]);
    }

    /**
     * Clear all signals for a user (e.g., after investigation).
     */
    public function clearUserSignals(?string $userId): void
    {
        if ($userId === null) {
            return;
        }

        Cache::forget("fraud:user:{$userId}:signals");
        Cache::forget("fraud:user:{$userId}:flagged");
        Cache::forget("fraud:user:{$userId}:flag_reason");
    }

    /**
     * Get statistics for fraud signals.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(int $windowHours = 24): array
    {
        $key = "fraud:stats:{$windowHours}h";

        return Cache::remember($key, 300, function () use ($windowHours) {
            if (! $this->shouldPersist()) {
                return [
                    'total_signals' => 0,
                    'high_severity' => 0,
                    'medium_severity' => 0,
                    'low_severity' => 0,
                    'unique_users' => 0,
                    'unique_ips' => 0,
                ];
            }

            $cutoff = now()->subHours($windowHours);
            $table = $this->configuration['table'] ?? 'cart_fraud_signals';

            $stats = DB::table($table)
                ->where('created_at', '>=', $cutoff)
                ->selectRaw('COUNT(*) as total_signals')
                ->selectRaw('SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) as high_severity')
                ->selectRaw('SUM(CASE WHEN score >= 50 AND score < 80 THEN 1 ELSE 0 END) as medium_severity')
                ->selectRaw('SUM(CASE WHEN score < 50 THEN 1 ELSE 0 END) as low_severity')
                ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
                ->selectRaw('COUNT(DISTINCT ip_address) as unique_ips')
                ->first();

            return (array) $stats;
        });
    }

    /**
     * Record signals for user tracking.
     */
    private function recordForUser(?string $userId, Collection $signals): void
    {
        if ($userId === null || $signals->isEmpty()) {
            return;
        }

        $key = "fraud:user:{$userId}:signals";
        $existing = Cache::get($key, []);

        foreach ($signals as $signal) {
            $existing[] = array_merge($signal->toArray(), [
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $existing = array_slice($existing, -100);

        Cache::put($key, $existing, self::CACHE_TTL);
    }

    /**
     * Record signals for IP tracking.
     */
    private function recordForIp(?string $ipAddress, Collection $signals): void
    {
        if ($ipAddress === null || $signals->isEmpty()) {
            return;
        }

        $key = "fraud:ip:{$ipAddress}:signals";
        $existing = Cache::get($key, []);

        foreach ($signals as $signal) {
            $existing[] = array_merge($signal->toArray(), [
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $existing = array_slice($existing, -100);

        Cache::put($key, $existing, self::CACHE_TTL);
    }

    /**
     * Record signals for session tracking.
     */
    private function recordForSession(?string $sessionId, Collection $signals): void
    {
        if ($sessionId === null || $signals->isEmpty()) {
            return;
        }

        $key = "fraud:session:{$sessionId}:signals";
        $existing = Cache::get($key, []);

        foreach ($signals as $signal) {
            $existing[] = array_merge($signal->toArray(), [
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        Cache::put($key, $existing, 3600);
    }

    /**
     * Persist signals to database.
     */
    private function persistToDatabase(FraudContext $context, Collection $signals): void
    {
        $table = $this->configuration['table'] ?? 'cart_fraud_signals';

        foreach ($signals as $signal) {
            try {
                DB::table($table)->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'cart_id' => $context->getCartId(),
                    'user_id' => $context->userId,
                    'ip_address' => $context->ipAddress,
                    'session_id' => $context->sessionId,
                    'signal_type' => $signal->type,
                    'detector' => $signal->detector,
                    'score' => $signal->score,
                    'message' => $signal->message,
                    'metadata' => json_encode($signal->metadata),
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::warning('Failed to persist fraud signal', [
                    'error' => $e->getMessage(),
                    'signal' => $signal->toArray(),
                ]);
            }
        }
    }

    /**
     * Check if signals should be persisted to database.
     */
    private function shouldPersist(): bool
    {
        return $this->configuration['persist_to_database'] ?? false;
    }

    /**
     * Get cache key for context.
     */
    private function getCacheKey(FraudContext $context): string
    {
        return self::CACHE_PREFIX.$context->getCartId().':'.$context->timestamp->format('YmdHis');
    }

    /**
     * Log detected signals.
     */
    private function logSignals(FraudContext $context, Collection $signals): void
    {
        $totalScore = $signals->sum('score');

        Log::info('Fraud signals detected', [
            'cart_id' => $context->getCartId(),
            'user_id' => $context->userId,
            'ip_address' => $context->ipAddress,
            'signal_count' => $signals->count(),
            'total_score' => $totalScore,
            'signals' => $signals->map(fn (FraudSignal $s) => [
                'type' => $s->type,
                'score' => $s->score,
                'message' => $s->message,
            ])->toArray(),
        ]);
    }
}
