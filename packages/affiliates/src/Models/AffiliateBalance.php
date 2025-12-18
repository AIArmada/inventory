<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $currency
 * @property int $holding_minor
 * @property int $available_minor
 * @property int $lifetime_earnings_minor
 * @property int $minimum_payout_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 */
class AffiliateBalance extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'currency',
        'holding_minor',
        'available_minor',
        'lifetime_earnings_minor',
        'minimum_payout_minor',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.balances', 'affiliate_balances');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function getTotalBalanceMinor(): int
    {
        return $this->holding_minor + $this->available_minor;
    }

    public function canRequestPayout(): bool
    {
        return $this->available_minor >= $this->minimum_payout_minor;
    }

    public function addToHolding(int $amountMinor): void
    {
        $this->increment('holding_minor', $amountMinor);
        $this->increment('lifetime_earnings_minor', $amountMinor);
    }

    public function releaseFromHolding(int $amountMinor): void
    {
        $releaseAmount = min($amountMinor, $this->holding_minor);
        $this->decrement('holding_minor', $releaseAmount);
        $this->increment('available_minor', $releaseAmount);
    }

    public function deductFromAvailable(int $amountMinor): void
    {
        $deductAmount = min($amountMinor, $this->available_minor);
        $this->decrement('available_minor', $deductAmount);
    }

    public function formatHolding(): string
    {
        return $this->formatAmount($this->holding_minor);
    }

    public function formatAvailable(): string
    {
        return $this->formatAmount($this->available_minor);
    }

    public function formatLifetimeEarnings(): string
    {
        return $this->formatAmount($this->lifetime_earnings_minor);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'holding_minor' => 'integer',
            'available_minor' => 'integer',
            'lifetime_earnings_minor' => 'integer',
            'minimum_payout_minor' => 'integer',
        ];
    }

    private function formatAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2);
    }
}
