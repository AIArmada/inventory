<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Trait for models that support multi-tenancy through owner scoping.
 *
 * This trait provides a standardized way to add owner-based multi-tenancy
 * to Eloquent models. Models using this trait should have `owner_type`
 * and `owner_id` columns in their database table.
 *
 * @method static Builder forOwner(?Model $owner, bool $includeGlobal = true)
 *
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 */
trait HasOwner // @phpstan-ignore trait.unused
{
    /**
     * Get the owner model (polymorphic relationship).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  Model|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner, bool $includeGlobal = true): Builder
    {
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

    /**
     * Scope query to only global (ownerless) records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGlobalOnly(Builder $query): Builder
    {
        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    /**
     * Check if this model has an owner assigned.
     */
    public function hasOwner(): bool
    {
        return $this->owner_type !== null && $this->owner_id !== null;
    }

    /**
     * Check if this model is global (no owner).
     */
    public function isGlobal(): bool
    {
        return ! $this->hasOwner();
    }

    /**
     * Check if this model belongs to the given owner.
     */
    public function belongsToOwner(Model $owner): bool
    {
        return $this->owner_type === $owner->getMorphClass()
            && $this->owner_id === $owner->getKey();
    }

    /**
     * Assign an owner to this model.
     */
    public function assignOwner(Model $owner): static
    {
        $this->owner_type = $owner->getMorphClass();
        $this->owner_id = $owner->getKey();

        return $this;
    }

    /**
     * Remove the owner from this model (make it global).
     */
    public function removeOwner(): static
    {
        $this->owner_type = null;
        $this->owner_id = null;

        return $this;
    }

    /**
     * Get the human-readable display name for the owner.
     */
    public function getOwnerDisplayNameAttribute(): ?string
    {
        $owner = $this->owner;

        if (! $owner) {
            return null;
        }

        if (method_exists($owner, 'getAttribute')) {
            /** @var string|null $name */
            $name = $owner->getAttribute('name');
            /** @var string|null $displayName */
            $displayName = $owner->getAttribute('display_name');
            /** @var string|null $email */
            $email = $owner->getAttribute('email');
            /** @var int|string $key */
            $key = $owner->getKey();

            return $name ?? $displayName ?? $email ?? class_basename($owner).':'.(string) $key;
        }

        /** @var int|string $key */
        $key = $owner->getKey();

        return class_basename($owner).':'.(string) $key;
    }
}
