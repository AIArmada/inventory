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
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $product_id
 * @property string $name
 * @property string|null $display_name
 * @property int $position
 * @property bool $is_visible
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Product $product
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OptionValue> $values
 */
class Option extends Model
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
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['options'] ?? $prefix . 'options';
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

        /** @var \Illuminate\Database\Eloquent\Builder<Option> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the option values.
     *
     * @return HasMany<OptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class, 'option_id')->orderBy('position');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (Option $option): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $option->owner_type !== null;
            $hasOwnerId = $option->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $currentOwner = OwnerContext::resolve();

            if ($currentOwner !== null && $hasOwnerType && ! $option->belongsToOwner($currentOwner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: option owner does not match the current owner context.');
            }

            $product = Product::query()->withoutOwnerScope()->whereKey($option->product_id)->first();

            if ($product === null) {
                throw new InvalidArgumentException('Invalid product_id: product not found.');
            }

            if ($product !== null && $currentOwner !== null) {
                $includeGlobal = (bool) config('products.features.owner.include_global', false);

                if (! $product->belongsToOwner($currentOwner) && ! ($includeGlobal && $product->isGlobal())) {
                    throw new InvalidArgumentException('Cross-tenant write blocked: option product does not belong to the current owner context.');
                }
            }

            if ($hasOwnerType) {
                $productOwner = $product->owner;
                if ($productOwner !== null && ! $option->belongsToOwner($productOwner)) {
                    throw new InvalidArgumentException('Cross-tenant write blocked: option owner does not match its product owner.');
                }

                return;
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            $ownerToAssign = $product?->owner ?? $currentOwner;

            if ($ownerToAssign === null) {
                return;
            }

            $option->assignOwner($ownerToAssign);
        });

        static::deleting(function (Option $option): void {
            $option->values()->delete();
        });
    }
}
