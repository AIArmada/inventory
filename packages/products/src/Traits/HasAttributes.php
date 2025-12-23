<?php

declare(strict_types=1);

namespace AIArmada\Products\Traits;

use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait to add dynamic attribute support to models.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasAttributes
{
    /**
     * Get all attribute values for this model.
     *
     * @return MorphMany<AttributeValue, $this>
     */
    public function attributeValues(): MorphMany
    {
        return $this->morphMany(AttributeValue::class, 'attributable');
    }

    /**
     * Get a single custom attribute value by code.
     */
    public function getCustomAttribute(string $code, ?string $locale = null): mixed
    {
        $value = $this->attributeValues()
            ->whereHas('attribute', fn ($q) => $q->where('code', $code))
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->first();

        return $value?->typed_value;
    }

    /**
     * Set a custom attribute value by code.
     */
    public function setCustomAttribute(string $code, mixed $value, ?string $locale = null): AttributeValue
    {
        $attribute = Attribute::where('code', $code)->firstOrFail();

        $attributeValue = $this->attributeValues()->updateOrCreate(
            [
                'attribute_id' => $attribute->id,
                'locale' => $locale,
            ],
            [
                'value' => $attribute->serializeValue($value),
            ]
        );

        return $attributeValue;
    }

    /**
     * Set multiple custom attribute values at once.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function setCustomAttributes(array $attributes, ?string $locale = null): void
    {
        foreach ($attributes as $code => $value) {
            $this->setCustomAttribute($code, $value, $locale);
        }
    }

    /**
     * Get all custom attributes as an associative array.
     *
     * @return array<string, mixed>
     */
    public function getCustomAttributesArray(?string $locale = null): array
    {
        return $this->attributeValues()
            ->with('attribute')
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->get()
            ->mapWithKeys(fn (AttributeValue $v) => [$v->attribute->code => $v->typed_value])
            ->toArray();
    }

    /**
     * Check if this model has a value for the given custom attribute.
     */
    public function hasCustomAttribute(string $code, ?string $locale = null): bool
    {
        return $this->attributeValues()
            ->whereHas('attribute', fn ($q) => $q->where('code', $code))
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->exists();
    }

    /**
     * Remove a custom attribute value.
     */
    public function removeCustomAttribute(string $code, ?string $locale = null): bool
    {
        return (bool) $this->attributeValues()
            ->whereHas('attribute', fn ($q) => $q->where('code', $code))
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->delete();
    }

    /**
     * Remove all custom attribute values.
     */
    public function clearCustomAttributes(?string $locale = null): int
    {
        return $this->attributeValues()
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->delete();
    }

    /**
     * Get custom attributes that are filterable.
     *
     * @return array<string, mixed>
     */
    public function getFilterableCustomAttributes(?string $locale = null): array
    {
        return $this->attributeValues()
            ->with('attribute')
            ->whereHas('attribute', fn ($q) => $q->where('is_filterable', true))
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->get()
            ->mapWithKeys(fn (AttributeValue $v) => [$v->attribute->code => $v->typed_value])
            ->toArray();
    }

    /**
     * Get custom attributes that are visible on frontend.
     *
     * @return array<string, mixed>
     */
    public function getVisibleCustomAttributes(?string $locale = null): array
    {
        return $this->attributeValues()
            ->with('attribute')
            ->whereHas('attribute', fn ($q) => $q->where('is_visible_on_front', true))
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->get()
            ->mapWithKeys(fn (AttributeValue $v) => [$v->attribute->code => $v->typed_value])
            ->toArray();
    }

    /**
     * Get custom attributes for comparison.
     *
     * @return array<string, array{label: string, value: mixed}>
     */
    public function getComparableCustomAttributes(?string $locale = null): array
    {
        return $this->attributeValues()
            ->with('attribute')
            ->whereHas('attribute', fn ($q) => $q->where('is_comparable', true))
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->when($locale === null, fn ($q) => $q->whereNull('locale'))
            ->get()
            ->mapWithKeys(fn (AttributeValue $v) => [
                $v->attribute->code => [
                    'label' => $v->attribute->name,
                    'value' => $v->typed_value,
                ],
            ])
            ->toArray();
    }

    /**
     * Scope to filter by custom attribute value.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWhereCustomAttribute($query, string $code, mixed $value, ?string $locale = null)
    {
        return $query->whereHas('attributeValues', function ($q) use ($code, $value, $locale): void {
            $q->whereHas('attribute', fn ($aq) => $aq->where('code', $code));

            if (is_array($value)) {
                $q->whereIn('value', $value);
            } else {
                $q->where('value', $value);
            }

            if ($locale !== null) {
                $q->where('locale', $locale);
            } else {
                $q->whereNull('locale');
            }
        });
    }

    /**
     * Scope to filter by multiple custom attributes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @param  array<string, mixed>  $attributes
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWhereCustomAttributes($query, array $attributes, ?string $locale = null)
    {
        foreach ($attributes as $code => $value) {
            $query->whereCustomAttribute($code, $value, $locale);
        }

        return $query;
    }

    /**
     * Boot the trait - clean up attribute values on delete.
     */
    protected static function bootHasAttributes(): void
    {
        static::deleting(function ($model): void {
            $model->attributeValues()->delete();
        });
    }
}
