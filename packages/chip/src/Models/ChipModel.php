<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\Chip\Models\Concerns\AutoAssignOwnerOnCreate;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string|null $owner_type
 * @property string|null $owner_id
 *
 * @method static Builder<static> forOwner(Model|null $owner = null, bool|null $includeGlobal = null)
 */
abstract class ChipModel extends Model implements Auditable
{
    use AutoAssignOwnerOnCreate;
    use HasCommerceAudit;
    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'chip.owner';

    public $timestamps = false;

    protected $guarded = [];

    abstract protected static function tableSuffix(): string;

    #[Override]
    final public function getTable(): string
    {
        $prefix = (string) config('chip.database.table_prefix', 'chip_');

        return $prefix . static::tableSuffix();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('chip.owner.enabled', true)) {
            return $query;
        }

        if ($owner === OwnerContext::CURRENT) {
            $owner = $this->resolveOwner();
        }

        $includeGlobal ??= (bool) config('chip.owner.include_global', false);

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwnerIncludingGlobal(Builder $query, Model | string | null $owner = OwnerContext::CURRENT): Builder
    {
        return $this->scopeForOwner($query, $owner, true);
    }

    final public function hasOwner(): bool
    {
        return $this->owner_type !== null && $this->owner_id !== null;
    }

    final public function isGlobal(): bool
    {
        return ! $this->hasOwner();
    }

    final public function assignOwner(Model $owner): static
    {
        $this->owner_type = $owner->getMorphClass();
        $this->owner_id = (string) $owner->getKey();

        return $this;
    }

    final public function removeOwner(): static
    {
        $this->owner_type = null;
        $this->owner_id = null;

        return $this;
    }

    // =========================================================================
    // AUDIT CONFIGURATION
    // =========================================================================

    /**
     * Get the attributes that should be audited for compliance.
     *
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        // Default attributes for all CHIP models - subclasses can override
        return [
            'status',
            'amount',
            'currency',
        ];
    }

    protected function resolveOwner(): ?Model
    {
        return \AIArmada\CommerceSupport\Support\OwnerContext::resolve();
    }

    protected function toTimestamp(?int $value): ?Carbon
    {
        return $value !== null ? Carbon::createFromTimestampUTC($value) : null;
    }

    /**
     * Convert an amount in cents to a Money object.
     *
     * @param  int|null  $amount  Amount in cents (smallest currency unit)
     * @param  string  $currency  ISO 4217 currency code (default: MYR)
     */
    protected function toMoney(?int $amount, string $currency = 'MYR'): ?Money
    {
        if ($amount === null) {
            return null;
        }

        return Money::{$currency}($amount);
    }

    /**
     * Get tags for categorizing this audit.
     *
     * @return array<int, string>
     */
    protected function getAuditTags(): array
    {
        return ['commerce', 'payments', 'chip'];
    }
}
