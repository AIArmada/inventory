<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $reference
 * @property string $status
 * @property int $total_minor
 * @property int $conversion_count
 * @property string $currency
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $amount_minor Alias for total_minor
 * @property-read string|null $external_reference From metadata
 * @property-read string|null $notes From metadata
 * @property-read Affiliate|null $affiliate Alias for owner when owner is an Affiliate
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateConversion> $conversions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliatePayoutEvent> $events
 */
class AffiliatePayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference',
        'status',
        'total_minor',
        'conversion_count',
        'currency',
        'metadata',
        'owner_type',
        'owner_id',
        'scheduled_at',
        'paid_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.payouts', parent::getTable());
    }

    /**
     * Polymorphic owner (typically an Affiliate).
     *
     * @return MorphTo<Model, self>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }

    /**
     * @return HasMany<AffiliatePayoutEvent, self>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AffiliatePayoutEvent::class, 'affiliate_payout_id')->latest();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $payout): void {
            $payout->events()->delete();
            $payout->conversions()->update(['affiliate_payout_id' => null]);
        });
    }

    /**
     * Get the affiliate (alias for owner when owner is an Affiliate).
     *
     * @return Attribute<Affiliate|null, never>
     */
    protected function affiliate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->owner instanceof Affiliate ? $this->owner : null,
        );
    }

    /**
     * Alias for total_minor (for code compatibility).
     *
     * @return Attribute<int, never>
     */
    protected function amountMinor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->total_minor,
        );
    }

    /**
     * Get external reference from metadata.
     *
     * @return Attribute<string|null, never>
     */
    protected function externalReference(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['external_reference'] ?? null,
        );
    }

    /**
     * Get notes from metadata.
     *
     * @return Attribute<string|null, never>
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->metadata['notes'] ?? null,
        );
    }
}
