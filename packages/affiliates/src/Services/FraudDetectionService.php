<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

final class FraudDetectionService
{
    /**
     * Analyze a click/visit for fraud signals.
     *
     * @return array{allowed: bool, score: int, signals: array<AffiliateFraudSignal>}
     */
    public function analyzeClick(Affiliate $affiliate, Request $request): array
    {
        if (! config('affiliates.fraud.enabled', true)) {
            return [
                'allowed' => true,
                'score' => 0,
                'signals' => [],
            ];
        }

        $signals = [];
        $context = $this->buildContext($affiliate, $request);

        // Velocity check
        if ($signal = $this->checkClickVelocity($affiliate, $context)) {
            $signals[] = $signal;
        }

        // Geo anomaly check
        if ($signal = $this->checkGeoAnomaly($affiliate, $context)) {
            $signals[] = $signal;
        }

        // Fingerprint check
        if ($signal = $this->checkFingerprint($affiliate, $context)) {
            $signals[] = $signal;
        }

        $score = collect($signals)->sum('risk_points');
        $allowed = $score < config('affiliates.fraud.blocking_threshold', 100);

        return [
            'allowed' => $allowed,
            'score' => $score,
            'signals' => $signals,
        ];
    }

    /**
     * Analyze a conversion for fraud signals.
     *
     * @return array{allowed: bool, score: int, signals: array<AffiliateFraudSignal>}
     */
    public function analyzeConversion(AffiliateConversion $conversion): array
    {
        if (! config('affiliates.fraud.enabled', true)) {
            return [
                'allowed' => true,
                'score' => 0,
                'signals' => [],
            ];
        }

        $signals = [];
        $affiliate = $conversion->affiliate;

        // Self-referral check
        if ($signal = $this->checkSelfReferral($conversion)) {
            $signals[] = $signal;
        }

        // Conversion velocity check
        if ($signal = $this->checkConversionVelocity($affiliate, $conversion)) {
            $signals[] = $signal;
        }

        // Click-to-conversion time check
        if ($signal = $this->checkClickToConversionTime($conversion)) {
            $signals[] = $signal;
        }

        $score = collect($signals)->sum('risk_points');
        $allowed = $score < config('affiliates.fraud.blocking_threshold', 100);

        return [
            'allowed' => $allowed,
            'score' => $score,
            'signals' => $signals,
        ];
    }

    /**
     * Get the fraud risk profile for an affiliate.
     *
     * @return array<string, mixed>
     */
    public function getRiskProfile(Affiliate $affiliate): array
    {
        $signals = AffiliateFraudSignal::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('detected_at', '>=', now()->subDays(30))
            ->get();

        $totalScore = $signals->sum('risk_points');
        $severity = FraudSeverity::fromScore($totalScore);

        $byRule = $signals->groupBy('rule_code')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'total_points' => $group->sum('risk_points'),
            ]);

        return [
            'total_score' => $totalScore,
            'severity' => $severity,
            'signal_count' => $signals->count(),
            'by_rule' => $byRule->toArray(),
            'pending_review' => $signals->where('status', FraudSignalStatus::Detected)->count(),
            'confirmed' => $signals->where('status', FraudSignalStatus::Confirmed)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Affiliate $affiliate, Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fingerprint' => $this->generateFingerprint($request),
            'referrer' => $request->header('Referer'),
            'timestamp' => now(),
        ];
    }

    private function generateFingerprint(Request $request): string
    {
        return hash('sha256', $request->userAgent() . '|' . $request->ip());
    }

    private function checkClickVelocity(Affiliate $affiliate, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.velocity', []);

        if (! ($config['enabled'] ?? true)) {
            return null;
        }

        $maxPerHour = $config['max_clicks_per_hour'] ?? 100;
        $cacheKey = "fraud:clicks:{$affiliate->id}:{$context['ip_address']}";

        $currentCount = (int) Cache::get($cacheKey, 0);

        if ($currentCount >= $maxPerHour) {
            return $this->createSignal(
                affiliate: $affiliate,
                ruleCode: 'CLICK_VELOCITY',
                riskPoints: 30,
                severity: FraudSeverity::Medium,
                description: "Click velocity exceeded: {$currentCount}/{$maxPerHour} per hour",
                evidence: [
                    'count' => $currentCount,
                    'limit' => $maxPerHour,
                    'ip_address' => $context['ip_address'],
                ]
            );
        }

        Cache::put($cacheKey, $currentCount + 1, now()->addHour());

        return null;
    }

    private function checkGeoAnomaly(Affiliate $affiliate, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.anomaly.geo', []);

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        $lastTouchpoint = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('ip_address', '!=', $context['ip_address'])
            ->latest('touched_at')
            ->first();

        if (! $lastTouchpoint) {
            return null;
        }

        // For now, just flag if same affiliate has visits from very different IPs in short time
        $timeDiff = now()->diffInMinutes($lastTouchpoint->touched_at);

        if ($timeDiff < 5 && $lastTouchpoint->ip_address !== $context['ip_address']) {
            return $this->createSignal(
                affiliate: $affiliate,
                ruleCode: 'GEO_ANOMALY',
                riskPoints: 40,
                severity: FraudSeverity::High,
                description: "Rapid IP change detected within {$timeDiff} minutes",
                evidence: [
                    'previous_ip' => $lastTouchpoint->ip_address,
                    'current_ip' => $context['ip_address'],
                    'time_diff_minutes' => $timeDiff,
                ]
            );
        }

        return null;
    }

    private function checkFingerprint(Affiliate $affiliate, array $context): ?AffiliateFraudSignal
    {
        $config = config('affiliates.tracking.fingerprint', []);

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        $threshold = max(1, (int) ($config['threshold'] ?? 5));

        $existingCount = AffiliateTouchpoint::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('metadata->fingerprint', $context['fingerprint'])
            ->where('touched_at', '>=', now()->subHours(24))
            ->count();

        if ($existingCount >= $threshold) {
            return $this->createSignal(
                affiliate: $affiliate,
                ruleCode: 'FINGERPRINT_REPEAT',
                riskPoints: 25,
                severity: FraudSeverity::Medium,
                description: "Same fingerprint used {$existingCount} times in 24 hours",
                evidence: [
                    'fingerprint' => mb_substr($context['fingerprint'], 0, 16) . '...',
                    'count' => $existingCount,
                    'threshold' => $threshold,
                ]
            );
        }

        return null;
    }

    private function checkSelfReferral(AffiliateConversion $conversion): ?AffiliateFraudSignal
    {
        if (! config('affiliates.tracking.block_self_referral', false)) {
            return null;
        }

        $affiliate = $conversion->affiliate;

        if ($affiliate->owner_id && $conversion->owner_id === $affiliate->owner_id) {
            return $this->createSignal(
                affiliate: $affiliate,
                ruleCode: 'SELF_REFERRAL',
                riskPoints: 100,
                severity: FraudSeverity::Critical,
                description: 'Self-referral detected',
                evidence: [
                    'affiliate_owner_id' => $affiliate->owner_id,
                    'conversion_owner_id' => $conversion->owner_id,
                ],
                conversionId: $conversion->id
            );
        }

        return null;
    }

    private function checkConversionVelocity(Affiliate $affiliate, AffiliateConversion $conversion): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.velocity', []);
        $maxDaily = $config['max_conversions_per_day'] ?? 50;

        $todayCount = $affiliate->conversions()
            ->whereDate('occurred_at', today())
            ->count();

        if ($todayCount >= $maxDaily) {
            return $this->createSignal(
                affiliate: $affiliate,
                ruleCode: 'CONVERSION_VELOCITY',
                riskPoints: 35,
                severity: FraudSeverity::Medium,
                description: "Daily conversion limit exceeded: {$todayCount}/{$maxDaily}",
                evidence: [
                    'count' => $todayCount,
                    'limit' => $maxDaily,
                    'date' => today()->toDateString(),
                ],
                conversionId: $conversion->id
            );
        }

        return null;
    }

    private function checkClickToConversionTime(AffiliateConversion $conversion): ?AffiliateFraudSignal
    {
        $config = config('affiliates.fraud.anomaly.conversion_time', []);
        $minSeconds = $config['min_seconds'] ?? 5;

        $attribution = $conversion->attribution;

        if (! $attribution) {
            return null;
        }

        $secondsSinceClick = $attribution->first_seen_at->diffInSeconds($conversion->occurred_at);

        if ($secondsSinceClick < $minSeconds) {
            return $this->createSignal(
                affiliate: $conversion->affiliate,
                ruleCode: 'FAST_CONVERSION',
                riskPoints: 45,
                severity: FraudSeverity::High,
                description: "Conversion occurred {$secondsSinceClick}s after click (min: {$minSeconds}s)",
                evidence: [
                    'click_time' => $attribution->first_seen_at->toIso8601String(),
                    'conversion_time' => $conversion->occurred_at->toIso8601String(),
                    'seconds' => $secondsSinceClick,
                    'min_seconds' => $minSeconds,
                ],
                conversionId: $conversion->id
            );
        }

        return null;
    }

    private function createSignal(
        Affiliate $affiliate,
        string $ruleCode,
        int $riskPoints,
        FraudSeverity $severity,
        string $description,
        array $evidence = [],
        ?string $conversionId = null,
        ?string $touchpointId = null
    ): AffiliateFraudSignal {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'conversion_id' => $conversionId,
            'touchpoint_id' => $touchpointId,
            'rule_code' => $ruleCode,
            'risk_points' => $riskPoints,
            'severity' => $severity,
            'description' => $description,
            'evidence' => $evidence,
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        Event::dispatch(new FraudSignalDetected($signal));

        return $signal;
    }
}
