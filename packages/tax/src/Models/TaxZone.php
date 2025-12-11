<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a geographic tax zone (Country, State, Postcode range).
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property array|null $countries
 * @property array|null $states
 * @property array|null $postcodes
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 */
class TaxZone extends Model
{
    use HasOwner;
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'countries' => 'array',
        'states' => 'array',
        'postcodes' => 'array',
        'priority' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'country',
        'priority' => 0,
        'is_default' => false,
        'is_active' => true,
    ];

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Get a zero-rate zone (for tax-free calculations).
     */
    public static function zeroRate(): self
    {
        return new self([
            'id' => 'zero-rate',
            'name' => 'Zero Rate Zone',
            'code' => 'ZERO',
            'is_active' => true,
        ]);
    }

    public function getTable(): string
    {
        return config('tax.tables.tax_zones', 'tax_zones');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * @return HasMany<TaxRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'zone_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForAddress($query, string $country, ?string $state = null, ?string $postcode = null)
    {
        return $query->active()
            ->where(function ($q) use ($country, $state): void {
                // Match by country
                $q->whereJsonContains('countries', $country);

                // Optionally match by state
                if ($state) {
                    $q->orWhereJsonContains('states', $state);
                }

                // Postcode matching would need custom logic
            })
            ->orderBy('priority', 'desc');
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('tax.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    // =========================================================================
    // MATCHING
    // =========================================================================

    /**
     * Check if an address matches this zone.
     */
    public function matchesAddress(string $country, ?string $state = null, ?string $postcode = null): bool
    {
        // Check country match
        if (! empty($this->countries) && ! in_array($country, $this->countries)) {
            return false;
        }

        // Check state match (if states are specified)
        if (! empty($this->states) && $state && ! in_array($state, $this->states)) {
            return false;
        }

        // Check postcode match (if postcodes are specified)
        if (! empty($this->postcodes) && $postcode) {
            $matches = false;
            foreach ($this->postcodes as $pattern) {
                if ($this->matchesPostcode($postcode, $pattern)) {
                    $matches = true;

                    break;
                }
            }
            if (! $matches) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'countries', 'states', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (TaxZone $zone): void {
            $zone->rates()->delete();
        });
    }

    /**
     * Match postcode against a pattern (supports wildcards and ranges).
     */
    protected function matchesPostcode(string $postcode, string $pattern): bool
    {
        // Exact match
        if ($postcode === $pattern) {
            return true;
        }

        // Range match (e.g., "10000-19999")
        if (str_contains($pattern, '-')) {
            [$start, $end] = explode('-', $pattern);
            $numericPostcode = (int) preg_replace('/[^0-9]/', '', $postcode);

            return $numericPostcode >= (int) $start && $numericPostcode <= (int) $end;
        }

        // Wildcard match (e.g., "100*")
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';

            return (bool) preg_match($regex, $postcode);
        }

        return false;
    }
}
