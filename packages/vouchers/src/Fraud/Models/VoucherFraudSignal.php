<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Models;

use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $voucher_id
 * @property string|null $voucher_code
 * @property FraudSignalType $signal_type
 * @property float $score
 * @property FraudRiskLevel $risk_level
 * @property string $message
 * @property string $detector
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $context
 * @property string|null $user_id
 * @property string|null $ip_address
 * @property string|null $device_fingerprint
 * @property bool $was_blocked
 * @property bool $reviewed
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Voucher|null $voucher
 */
class VoucherFraudSignal extends Model
{
    use HasUuids;

    protected $fillable = [
        'voucher_id',
        'voucher_code',
        'signal_type',
        'score',
        'risk_level',
        'message',
        'detector',
        'metadata',
        'context',
        'user_id',
        'ip_address',
        'device_fingerprint',
        'was_blocked',
        'reviewed',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['voucher_fraud_signals'] ?? $prefix.'voucher_fraud_signals';
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Mark the signal as reviewed.
     */
    public function markReviewed(?string $reviewerId = null, ?string $notes = null): bool
    {
        return $this->update([
            'reviewed' => true,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    /**
     * Scope to get unreviewed signals.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeUnreviewed($query)
    {
        return $query->where('reviewed', false);
    }

    /**
     * Scope to get blocked signals.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeBlocked($query)
    {
        return $query->where('was_blocked', true);
    }

    /**
     * Scope to get signals by risk level.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeByRiskLevel($query, FraudRiskLevel $level)
    {
        return $query->where('risk_level', $level);
    }

    /**
     * Scope to get high-risk signals (High or Critical).
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', [
            FraudRiskLevel::High,
            FraudRiskLevel::Critical,
        ]);
    }

    /**
     * Scope to get signals by detector.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeByDetector($query, string $detector)
    {
        return $query->where('detector', $detector);
    }

    /**
     * Scope to get signals by signal type.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeBySignalType($query, FraudSignalType $type)
    {
        return $query->where('signal_type', $type);
    }

    /**
     * Scope to get signals for a user.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get signals from an IP address.
     *
     * @param  Builder<VoucherFraudSignal>  $query
     * @return Builder<VoucherFraudSignal>
     */
    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Check if this signal requires review.
     */
    public function requiresReview(): bool
    {
        return ! $this->reviewed && $this->risk_level->requiresReview();
    }

    /**
     * Get a summary description of the signal.
     */
    public function getSummary(): string
    {
        return sprintf(
            '%s (%s) - Score: %.0f - %s',
            $this->signal_type->getLabel(),
            $this->risk_level->getLabel(),
            $this->score,
            $this->message
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (VoucherFraudSignal $signal): void {
            // No child relations to cascade
        });
    }

    protected function casts(): array
    {
        return [
            'signal_type' => FraudSignalType::class,
            'risk_level' => FraudRiskLevel::class,
            'score' => 'float',
            'metadata' => 'array',
            'context' => 'array',
            'was_blocked' => 'boolean',
            'reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
