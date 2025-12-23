<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Products\Enums\AttributeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property AttributeType $type
 * @property array<string, mixed>|null $validation
 * @property array<string, mixed>|null $options
 * @property bool $is_required
 * @property bool $is_filterable
 * @property bool $is_searchable
 * @property bool $is_comparable
 * @property bool $is_visible_on_front
 * @property bool $is_visible_on_admin
 * @property int $position
 * @property string|null $suffix
 * @property string|null $placeholder
 * @property string|null $help_text
 * @property string|null $default_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeGroup> $groups
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeSet> $attributeSets
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeValue> $values
 */
class Attribute extends Model
{
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => AttributeType::class,
        'validation' => 'array',
        'options' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_searchable' => 'boolean',
        'is_comparable' => 'boolean',
        'is_visible_on_front' => 'boolean',
        'is_visible_on_admin' => 'boolean',
        'position' => 'integer',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'text',
        'is_required' => false,
        'is_filterable' => false,
        'is_searchable' => false,
        'is_comparable' => false,
        'is_visible_on_front' => true,
        'is_visible_on_admin' => true,
        'position' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['attributes'] ?? $prefix . 'attributes';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('products.features.owner.include_global', false);
        }

        /** @var Builder<Attribute> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * Get the groups this attribute belongs to.
     *
     * @return BelongsToMany<AttributeGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeGroup::class,
            config('products.database.tables.attribute_attribute_group', 'attribute_attribute_group'),
            'attribute_id',
            'attribute_group_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Get the attribute sets this attribute belongs to.
     *
     * @return BelongsToMany<AttributeSet, $this>
     */
    public function attributeSets(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeSet::class,
            config('products.database.tables.attribute_attribute_set', 'attribute_attribute_set'),
            'attribute_id',
            'attribute_set_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * Get all values for this attribute.
     *
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id');
    }

    /**
     * Cast a raw value to the appropriate PHP type based on this attribute's type.
     */
    public function castValue(mixed $value): mixed
    {
        return $this->type->castValue($value);
    }

    /**
     * Serialize a value for storage based on this attribute's type.
     */
    public function serializeValue(mixed $value): ?string
    {
        return $this->type->serializeValue($value);
    }

    /**
     * Get the validation rules for this attribute.
     *
     * @return array<string>
     */
    public function getValidationRules(): array
    {
        $rules = $this->validation ?? $this->type->defaultValidation();

        if ($this->is_required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Check if this attribute has predefined options.
     */
    public function hasOptions(): bool
    {
        return $this->type->hasOptions() && ! empty($this->options);
    }

    /**
     * Get options as key-value pairs for select fields.
     *
     * @return array<string, string>
     */
    public function getOptionsArray(): array
    {
        if (! $this->hasOptions()) {
            return [];
        }

        $options = $this->options ?? [];

        // If options are simple array, use value as both key and label
        if (isset($options[0]) && ! is_array($options[0])) {
            return array_combine($options, $options);
        }

        // If options are associative with value/label keys
        $result = [];
        foreach ($options as $option) {
            if (is_array($option) && isset($option['value'])) {
                $result[$option['value']] = $option['label'] ?? $option['value'];
            }
        }

        return $result;
    }

    /**
     * Scope to filterable attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Attribute>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Attribute>
     */
    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    /**
     * Scope to searchable attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Attribute>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Attribute>
     */
    public function scopeSearchable($query)
    {
        return $query->where('is_searchable', true);
    }

    /**
     * Scope to comparable attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Attribute>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Attribute>
     */
    public function scopeComparable($query)
    {
        return $query->where('is_comparable', true);
    }

    /**
     * Scope to visible on front attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Attribute>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Attribute>
     */
    public function scopeVisibleOnFront($query)
    {
        return $query->where('is_visible_on_front', true);
    }

    /**
     * Scope to order by position.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Attribute>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Attribute>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    protected static function booted(): void
    {
        static::creating(function (Attribute $attribute): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $attribute->owner_type !== null;
            $hasOwnerId = $attribute->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null && $hasOwnerType && ! $attribute->belongsToOwner($owner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: attribute owner does not match the current owner context.');
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($attribute->owner_type !== null || $attribute->owner_id !== null) {
                return;
            }

            if ($owner === null) {
                return;
            }

            $attribute->assignOwner($owner);
        });

        static::deleting(function (Attribute $attribute): void {
            // Delete all attribute values
            $attribute->values()->delete();
            // Detach from groups and sets
            $attribute->groups()->detach();
            $attribute->attributeSets()->detach();
        });
    }
}
