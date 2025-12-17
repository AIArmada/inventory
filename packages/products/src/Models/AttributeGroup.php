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

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $position
 * @property bool $is_visible
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Attribute> $groupAttributes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeSet> $attributeSets
 */
class AttributeGroup extends Model
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
        'position' => 'integer',
        'is_visible' => 'boolean',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_visible' => true,
    ];

    public function getTable(): string
    {
        return config('products.tables.attribute_groups', 'product_attribute_groups');
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

        /** @var Builder<AttributeGroup> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    /**
     * Get the attributes in this group.
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function groupAttributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            config('products.tables.attribute_attribute_group', 'attribute_attribute_group'),
            'attribute_group_id',
            'attribute_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Get the attribute sets that include this group.
     *
     * @return BelongsToMany<AttributeSet, $this>
     */
    public function attributeSets(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeSet::class,
            config('products.tables.attribute_group_attribute_set', 'attribute_group_attribute_set'),
            'attribute_group_id',
            'attribute_set_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Scope to visible groups only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeGroup>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeGroup>
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope to order by position.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeGroup>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeGroup>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeGroup $group): void {
            if (! (bool) config('products.owner.enabled', true)) {
                return;
            }

            if (! (bool) config('products.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($group->owner_type !== null || $group->owner_id !== null) {
                return;
            }

            if (! app()->bound(OwnerResolverInterface::class)) {
                return;
            }

            $owner = app(OwnerResolverInterface::class)->resolve();

            if ($owner === null) {
                return;
            }

            $group->assignOwner($owner);
        });

        static::deleting(function (AttributeGroup $group): void {
            // Detach from attributes and attribute sets
            $group->groupAttributes()->detach();
            $group->attributeSets()->detach();
        });
    }
}
