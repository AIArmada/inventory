<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\PayoutMethodType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property PayoutMethodType $type
 * @property array<string, mixed> $details
 * @property bool $is_verified
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $label Computed label from type and details
 * @property-read Affiliate $affiliate
 */
class AffiliatePayoutMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'type',
        'details',
        'is_verified',
        'is_default',
        'verified_at',
    ];

    protected $casts = [
        'type' => PayoutMethodType::class,
        'details' => 'encrypted:array',
        'is_verified' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'details',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.payout_methods', 'affiliate_payout_methods');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function verify(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function setAsDefault(): void
    {
        // Remove default from other methods
        static::where('affiliate_id', $this->affiliate_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    public function getMaskedDetails(): array
    {
        $details = $this->details ?? [];

        return match ($this->type) {
            PayoutMethodType::BankTransfer => [
                'bank_name' => $details['bank_name'] ?? null,
                'account_last_4' => isset($details['account_number'])
                    ? '****'.mb_substr((string) $details['account_number'], -4)
                    : null,
            ],
            PayoutMethodType::PayPal => [
                'email' => isset($details['email'])
                    ? $this->maskEmail((string) $details['email'])
                    : null,
            ],
            PayoutMethodType::StripeConnect => [
                'account_id' => isset($details['stripe_account_id'])
                    ? mb_substr((string) $details['stripe_account_id'], 0, 8).'...'
                    : null,
            ],
            default => [],
        };
    }

    /**
     * Get a human-readable label for this payout method.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $details = $this->details ?? [];

                return match ($this->type) {
                    PayoutMethodType::BankTransfer => $details['bank_name'] ?? 'Bank Transfer',
                    PayoutMethodType::PayPal => $details['email'] ?? 'PayPal',
                    PayoutMethodType::StripeConnect => 'Stripe Connect',
                    default => $this->type?->value ?? 'Unknown',
                };
            },
        );
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $maskedName = mb_strlen($name) > 2
            ? mb_substr($name, 0, 2).str_repeat('*', mb_strlen($name) - 2)
            : str_repeat('*', mb_strlen($name));

        return $maskedName.'@'.$domain;
    }
}
