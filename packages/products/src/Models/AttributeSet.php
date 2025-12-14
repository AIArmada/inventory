<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasOwner;
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
        static::query()->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    protected static function booted(): void
    {
        static::deleting(function (AttributeSet $set): void {
            // Detach from attributes and groups
            $set->setAttributes()->detach();
            $set->groups()->detach();
        });
    }
}
