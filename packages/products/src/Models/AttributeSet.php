<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_default
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Attribute> $setAttributes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeGroup> $groups
 */
class AttributeSet extends Model
{
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'position' => 'integer',
    ];

    public function getTable(): string
    {
        return config('products.tables.attribute_sets', 'product_attribute_sets');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = true): Builder
    {
        if (! (bool) config('products.owner.enabled', true)) {
            return $query;
        }

        if ($owner === null && app()->bound(OwnerResolverInterface::class)) {
            $owner = app(OwnerResolverInterface::class)->resolve();
        }

        $includeGlobal = $includeGlobal && (bool) config('products.owner.include_global', true);

        /** @var Builder<AttributeSet> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    /**
     * Get the attributes in this set.
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function setAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            config('products.tables.attribute_attribute_set', 'attribute_attribute_set'),
            'attribute_set_id',
            'attribute_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Get the attribute groups in this set.
     *
     * @return BelongsToMany<AttributeGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeGroup::class,
            config('products.tables.attribute_group_attribute_set', 'attribute_group_attribute_set'),
            'attribute_set_id',
            'attribute_group_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Get all attributes organized by group.
     *
     * @return \Illuminate\Support\Collection<int, array{group: AttributeGroup, attributes: \Illuminate\Database\Eloquent\Collection<int, Attribute>}>
     */
    public function getGroupedAttributes(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['groups.groupAttributes', 'setAttributes']);

        return $this->groups->map(fn (AttributeGroup $group) => [
            'group' => $group,
            'attributes' => $group->groupAttributes->filter(
                fn (Attribute $attr) => $this->setAttributes->contains('id', $attr->id)
            ),
        ]);
    }

    /**
     * Scope to default sets only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeSet>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeSet>
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to order by position.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeSet>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeSet>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Mark this set as the default, unsetting others.
     */
    public function setAsDefault(): void
    {
        DB::transaction(function (): void {
            $query = static::query();

            if ($this->owner_type === null || $this->owner_id === null) {
                $query->whereNull('owner_type')->whereNull('owner_id');
            } else {
                $query->where('owner_type', $this->owner_type)
                    ->where('owner_id', $this->owner_id);
            }

            $query->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeSet $set): void {
            if (! (bool) config('products.owner.enabled', true)) {
                return;
            }

            if (! (bool) config('products.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($set->owner_type !== null || $set->owner_id !== null) {
                return;
            }

            if (! app()->bound(OwnerResolverInterface::class)) {
                return;
            }

            $owner = app(OwnerResolverInterface::class)->resolve();

            if ($owner === null) {
                return;
            }

            $set->assignOwner($owner);
        });

        static::deleting(function (AttributeSet $set): void {
            // Detach from attributes and groups
            $set->setAttributes()->detach();
            $set->groups()->detach();
        });
    }
}
