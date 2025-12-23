<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $option_id
 * @property string $name
 * @property int $position
 * @property string|null $swatch_color
 * @property string|null $swatch_image
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Option $option
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Variant> $variants
 */
class OptionValue extends Model
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
        'position' => 'integer',
        'swatch_color' => 'string',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['option_values'] ?? $prefix . 'option_values';
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

        /** @var \Illuminate\Database\Eloquent\Builder<OptionValue> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent option.
     *
     * @return BelongsTo<Option, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }

    /**
     * Get the variants using this option value.
     *
     * @return BelongsToMany<Variant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            Variant::class,
            config('products.database.tables.variant_options', 'product_variant_options'),
            'option_value_id',
            'variant_id'
        );
    }

    // =========================================================================
    // SWATCH HELPERS
    // =========================================================================

    /**
     * Check if this option value has a color swatch.
     */
    public function hasColorSwatch(): bool
    {
        return ! empty($this->swatch_color);
    }

    /**
     * Check if this option value has an image swatch.
     */
    public function hasImageSwatch(): bool
    {
        return ! empty($this->swatch_image);
    }

    /**
     * Get the swatch style for CSS.
     */
    public function getSwatchStyle(): ?string
    {
        if ($this->hasColorSwatch()) {
            return "background-color: {$this->swatch_color}";
        }

        if ($this->hasImageSwatch()) {
            return "background-image: url('{$this->swatch_image}')";
        }

        return null;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (OptionValue $optionValue): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $optionValue->owner_type !== null;
            $hasOwnerId = $optionValue->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $currentOwner = OwnerContext::resolve();

            if ($currentOwner !== null && $hasOwnerType && ! $optionValue->belongsToOwner($currentOwner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: option value owner does not match the current owner context.');
            }

            $option = Option::query()->withoutOwnerScope()->whereKey($optionValue->option_id)->first();

            if ($option === null) {
                throw new InvalidArgumentException('Invalid option_id: option not found.');
            }

            $product = null;
            if ($option !== null) {
                $product = Product::query()->withoutOwnerScope()->whereKey($option->product_id)->first();
            }

            if ($product === null) {
                throw new InvalidArgumentException('Invalid product_id: option product not found.');
            }

            if ($product !== null && $currentOwner !== null) {
                $includeGlobal = (bool) config('products.features.owner.include_global', false);

                if (! $product->belongsToOwner($currentOwner) && ! ($includeGlobal && $product->isGlobal())) {
                    throw new InvalidArgumentException('Cross-tenant write blocked: option value option/product does not belong to the current owner context.');
                }
            }

            if ($hasOwnerType) {
                $productOwner = $product->owner;
                if ($productOwner !== null && ! $optionValue->belongsToOwner($productOwner)) {
                    throw new InvalidArgumentException('Cross-tenant write blocked: option value owner does not match its product owner.');
                }

                $optionOwner = $option->owner;
                if ($productOwner === null && $optionOwner !== null && ! $optionValue->belongsToOwner($optionOwner)) {
                    throw new InvalidArgumentException('Cross-tenant write blocked: option value owner does not match its option owner.');
                }

                return;
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            $ownerToAssign = $product?->owner ?? $option?->owner ?? $currentOwner;

            if ($ownerToAssign === null) {
                return;
            }

            $optionValue->assignOwner($ownerToAssign);
        });

        static::deleting(function (OptionValue $optionValue): void {
            $optionValue->variants()->detach();
        });
    }
}
