<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use AIArmada\Chip\Models\Concerns\AutoAssignOwnerOnCreate;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * Base model for CHIP tables that use integer primary keys.
 *
 * Used by: BankAccountData, SendInstruction, SendLimit, SendWebhook
 * These tables mirror the CHIP Send API which uses integer IDs.
 */
abstract class ChipIntegerModel extends Model
{
    use AutoAssignOwnerOnCreate;
    use HasOwner {
        scopeForOwner as private scopeForOwnerUsingTrait;
    }
    use HasOwnerScopeConfig;

    protected static string $ownerScopeConfigKey = 'chip.owner';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'int';

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
    final public function scopeForOwner(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('chip.owner.enabled', true)) {
            return $query;
        }

        $owner ??= $this->resolveOwner();
        $includeGlobal ??= (bool) config('chip.owner.include_global', false);

        return $this->scopeForOwnerUsingTrait($query, $owner, $includeGlobal);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    final public function scopeForOwnerIncludingGlobal(Builder $query, ?Model $owner = null): Builder
    {
        return $this->scopeForOwner($query, $owner, true);
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
}
