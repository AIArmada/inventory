<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $attribute_id
 * @property string $attributable_type
 * @property string $attributable_id
 * @property string|null $value
 * @property string|null $locale
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Attribute $attribute
 * @property-read Model $attributable
 * @property-read mixed $typed_value
 */
class AttributeValue extends Model
{
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $guarded = ['id'];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['attribute_values'] ?? $prefix . 'attribute_values';
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForOwner(\Illuminate\Database\Eloquent\Builder $query, ?Model $owner = null, bool $includeGlobal = false): \Illuminate\Database\Eloquent\Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('products.features.owner.include_global', false);
        }

        /** @var \Illuminate\Database\Eloquent\Builder<AttributeValue> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    /**
     * Get the attribute definition.
     *
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    /**
     * Get the owning model (Product or Variant).
     *
     * @return MorphTo<Model, $this>
     */
    public function attributable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the value cast to the appropriate PHP type.
     */
    public function getTypedValueAttribute(): mixed
    {
        if (! $this->relationLoaded('attribute')) {
            $this->load('attribute');
        }

        return $this->attribute?->castValue($this->value);
    }

    /**
     * Set the value, serializing it appropriately.
     */
    public function setTypedValue(mixed $value): self
    {
        if (! $this->relationLoaded('attribute')) {
            $this->load('attribute');
        }

        $this->value = $this->attribute?->serializeValue($value);

        return $this;
    }

    /**
     * Scope to a specific locale.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeValue>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeValue>
     */
    public function scopeForLocale($query, ?string $locale = null)
    {
        return $query->where('locale', $locale);
    }

    /**
     * Scope to a specific attribute by code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<AttributeValue>  $query
     * @return \Illuminate\Database\Eloquent\Builder<AttributeValue>
     */
    public function scopeForAttribute($query, string $code)
    {
        return $query->whereHas('attribute', fn ($q) => $q->where('code', $code));
    }

    protected static function booted(): void
    {
        static::creating(function (AttributeValue $attributeValue): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $attributeValue->owner_type !== null;
            $hasOwnerId = $attributeValue->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $currentOwner = OwnerContext::resolve();

            if ($currentOwner !== null && $hasOwnerType && ! $attributeValue->belongsToOwner($currentOwner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: attribute value owner does not match the current owner context.');
            }

            $includeGlobal = (bool) config('products.features.owner.include_global', false);

            $attributable = null;
            $attributableType = Relation::getMorphedModel($attributeValue->attributable_type) ?? $attributeValue->attributable_type;

            if (is_string($attributableType) && class_exists($attributableType) && is_a($attributableType, Model::class, true)) {
                /** @var class-string<Model> $attributableType */
                $query = $attributableType::query();

                $query->withoutGlobalScope(OwnerScope::class);

                $attributable = $query->whereKey($attributeValue->attributable_id)->first();
            }

            if (! $attributable instanceof Model) {
                throw new InvalidArgumentException('Invalid attributable: unable to resolve attributable model for attribute value.');
            }

            if ($attributable instanceof Model && method_exists($attributable, 'belongsToOwner') && method_exists($attributable, 'isGlobal')) {
                if ($currentOwner !== null) {
                    /** @var bool $belongs */
                    $belongs = $attributable->belongsToOwner($currentOwner);

                    /** @var bool $isGlobal */
                    $isGlobal = $attributable->isGlobal();

                    if (! $belongs && ! ($includeGlobal && $isGlobal)) {
                        throw new InvalidArgumentException('Cross-tenant write blocked: attribute value attributable does not belong to the current owner context.');
                    }
                }

                /** @var Model|null $ownerToAssign */
                $ownerToAssign = $attributable->owner;

                if ($hasOwnerType && $ownerToAssign !== null && ! $attributeValue->belongsToOwner($ownerToAssign)) {
                    throw new InvalidArgumentException('Cross-tenant write blocked: attribute value owner does not match its attributable owner.');
                }

                if ($hasOwnerType) {
                    return;
                }

                if ($ownerToAssign !== null) {
                    if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                        return;
                    }

                    $attributeValue->assignOwner($ownerToAssign);

                    return;
                }
            }

            if ($currentOwner === null) {
                return;
            }

            if ($hasOwnerType) {
                return;
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            $attributeValue->assignOwner($currentOwner);
        });
    }
}
