<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use AIArmada\Inventory\Database\Factories\InventoryLocationFactory;
use AIArmada\Inventory\Enums\TemperatureZone;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Traits\HasLocationHierarchy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string|null $line1
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postcode
 * @property string|null $country
 * @property bool $is_active
 * @property int $priority
 * @property string|null $parent_id
 * @property string|null $path
 * @property int $depth
 * @property string|null $temperature_zone
 * @property bool $is_hazmat_certified
 * @property float|null $coordinate_x
 * @property float|null $coordinate_y
 * @property float|null $coordinate_z
 * @property int|null $pick_sequence
 * @property int|null $capacity
 * @property int $current_utilization
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, InventoryLevel> $inventoryLevels
 * @property-read Collection<int, InventoryMovement> $movementsFrom
 * @property-read Collection<int, InventoryMovement> $movementsTo
 * @property-read Collection<int, InventoryAllocation> $allocations
 * @property-read InventoryLocation|null $parent
 * @property-read Collection<int, InventoryLocation> $children
 * @property-read Collection<int, InventoryLocation> $descendants
 * @property-read Collection<int, InventoryLocation> $ancestors
 * @property-read string|null $owner_display_name
 */
final class InventoryLocation extends Model implements Auditable
{
    use HasCommerceAudit;

    /** @use HasFactory<InventoryLocationFactory> */
    use HasFactory;

    use HasLocationHierarchy;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    /** @var list<string> */
    protected $hidden = [
        'owner_scope',
    ];

    public const DEFAULT_LOCATION_CODE = 'DEFAULT';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'line1',
        'line2',
        'city',
        'state',
        'postcode',
        'country',
        'is_active',
        'priority',
        'parent_id',
        'path',
        'depth',
        'temperature_zone',
        'is_hazmat_certified',
        'coordinate_x',
        'coordinate_y',
        'coordinate_z',
        'pick_sequence',
        'capacity',
        'current_utilization',
        'metadata',
    ];

    /**
     * Get or create the default location for simple setups.
     */
    public static function getOrCreateDefault(): self
    {
        if (InventoryOwnerScope::isEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                InventoryOwnerScope::resolveOwner(),
                'InventoryLocation::getOrCreateDefault() requires an owner context or explicit global context.',
            );
        }

        return self::firstOrCreate(
            ['code' => self::DEFAULT_LOCATION_CODE],
            [
                'name' => 'Default Location',
                'is_active' => true,
                'priority' => 100,
            ]
        );
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('inventory.database.tables.locations', 'inventory_locations');
    }

    /**
     * Get all inventory levels at this location.
     *
     * @return HasMany<InventoryLevel, $this>
     */
    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class, 'location_id');
    }

    /**
     * Get movements originating from this location.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function movementsFrom(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'from_location_id');
    }

    /**
     * Get movements arriving at this location.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function movementsTo(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'to_location_id');
    }

    /**
     * Get all allocations at this location.
     *
     * @return HasMany<InventoryAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class, 'location_id');
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('inventory.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('inventory.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    /**
     * Scope to only active locations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority (highest first).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Check if this is the default location.
     */
    public function isDefault(): bool
    {
        return $this->code === self::DEFAULT_LOCATION_CODE;
    }

    /**
     * Get the temperature zone as enum.
     */
    public function getTemperatureZoneEnum(): ?TemperatureZone
    {
        if ($this->temperature_zone === null) {
            return null;
        }

        return TemperatureZone::from($this->temperature_zone);
    }

    /**
     * Check if this location can store items requiring a specific temperature zone.
     */
    public function canStoreTemperatureZone(TemperatureZone $required): bool
    {
        $current = $this->getTemperatureZoneEnum();

        // If no zone specified, assume ambient-compatible
        if ($current === null) {
            return $required === TemperatureZone::Ambient;
        }

        return $current->isCompatibleWith($required);
    }

    /**
     * Check if this location can store hazardous materials.
     */
    public function canStoreHazmat(): bool
    {
        return $this->is_hazmat_certified;
    }

    /**
     * Get coordinates as array.
     *
     * @return array{x: float|null, y: float|null, z: float|null}
     */
    public function getCoordinates(): array
    {
        return [
            'x' => $this->coordinate_x,
            'y' => $this->coordinate_y,
            'z' => $this->coordinate_z,
        ];
    }

    /**
     * Set coordinates.
     */
    public function setCoordinates(?float $x, ?float $y, ?float $z = null): self
    {
        $this->coordinate_x = $x;
        $this->coordinate_y = $y;
        $this->coordinate_z = $z;

        return $this;
    }

    /**
     * Calculate distance to another location.
     */
    public function distanceTo(self $other): ?float
    {
        if ($this->coordinate_x === null || $other->coordinate_x === null) {
            return null;
        }

        $dx = $this->coordinate_x - $other->coordinate_x;
        $dy = ($this->coordinate_y ?? 0) - ($other->coordinate_y ?? 0);
        $dz = ($this->coordinate_z ?? 0) - ($other->coordinate_z ?? 0);

        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    /**
     * Get the capacity utilization percentage.
     */
    public function getUtilizationPercentage(): ?float
    {
        if ($this->capacity === null || $this->capacity === 0) {
            return null;
        }

        return ($this->current_utilization / $this->capacity) * 100;
    }

    /**
     * Check if location has available capacity.
     */
    public function hasAvailableCapacity(int $required = 1): bool
    {
        if ($this->capacity === null) {
            return true; // No capacity limit
        }

        return ($this->capacity - $this->current_utilization) >= $required;
    }

    /**
     * Scope to filter by temperature zone.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithTemperatureZone(Builder $query, TemperatureZone $zone): Builder
    {
        return $query->where('temperature_zone', $zone->value);
    }

    /**
     * Scope to filter hazmat certified locations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeHazmatCertified(Builder $query): Builder
    {
        return $query->where('is_hazmat_certified', true);
    }

    /**
     * Scope to filter by available capacity.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithAvailableCapacity(Builder $query, int $required = 1): Builder
    {
        return $query->where(function (Builder $q) use ($required): void {
            $q->whereNull('capacity')
                ->orWhereRaw('(capacity - current_utilization) >= ?', [$required]);
        });
    }

    /**
     * Scope to order by pick sequence.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByPickSequence(Builder $query): Builder
    {
        return $query->orderBy('pick_sequence');
    }

    /**
     * Handle model lifecycle events.
     */
    protected static function booted(): void
    {
        static::creating(function (InventoryLocation $location): void {
            $location->updatePathAndDepth();
        });

        static::created(function (InventoryLocation $location): void {
            // Safety net for keys assigned after the creating hook: a root
            // path is never left null or placeholder-valued.
            if ($location->parent_id === null && $location->path === null) {
                $location->forceFill(['path' => $location->getKey(), 'depth' => 0])->saveQuietly();
            }
        });

        static::updating(function (InventoryLocation $location): void {
            if ($location->isDirty('parent_id')) {
                $location->updatePathAndDepth();
            }

            if ($location->isDirty('path')) {
                $location->rememberPreviousPathForRebuild();
            }
        });

        static::saved(function (InventoryLocation $location): void {
            if ($location->wasChanged('path') && $location->previousPathForRebuild !== null) {
                $location->rebuildDescendantPaths(
                    $location->previousPathForRebuild,
                    $location->previousDepthForRebuild ?? $location->depth
                );
                $location->previousPathForRebuild = null;
                $location->previousDepthForRebuild = null;
            }
        });

        static::saving(function (InventoryLocation $location): void {
            if (! InventoryOwnerScope::isEnabled()) {
                return;
            }

            $owner = InventoryOwnerScope::resolveOwner();

            if ($location->owner_type === null xor $location->owner_id === null) {
                throw new AuthorizationException('Owner fields must be both set or both null.');
            }

            if ($owner === null) {
                if ($location->owner_type !== null || $location->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned inventory locations without an owner context.');
                }

                return;
            }

            if ($location->owner_type !== null || $location->owner_id !== null) {
                if ($location->owner_type !== $owner->getMorphClass() || $location->owner_id !== $owner->getKey()) {
                    throw new AuthorizationException('Cannot write inventory locations for a different owner context.');
                }

                return;
            }

            if (! (bool) config('inventory.owner.auto_assign_on_create', true)) {
                return;
            }

            $location->assignOwner($owner);
        });

        static::deleting(function (InventoryLocation $location): void {
            // Re-parent through model saves (not a mass update) so each
            // child recomputes its path and depth via the normal hooks.
            $location->children()->chunkById(200, function ($children) use ($location): void {
                foreach ($children as $child) {
                    $child->update(['parent_id' => $location->parent_id]);
                }
            });
        });

        self::deleting(function (InventoryLocation $location): void {
            $location->inventoryLevels()->delete();
            $location->allocations()->delete();
            $location->movementsFrom()->delete();
            $location->movementsTo()->delete();

            $batchIds = InventoryBatch::query()
                ->where('location_id', $location->getKey())
                ->pluck('id');

            // Cost layers belong to batches, which are removed below with a
            // query delete that skips model events; clear them first so no
            // layer outlives its batch. Batch-linked allocations are already
            // gone via the location-wide allocation delete above.
            InventoryCostLayer::query()->whereIn('batch_id', $batchIds)->delete();
            InventoryBatch::query()->where('location_id', $location->getKey())->delete();

            // Per-model deletes so serial history cascades fire.
            InventorySerial::query()
                ->where('location_id', $location->getKey())
                ->chunkById(200, function ($serials): void {
                    foreach ($serials as $serial) {
                        $serial->delete();
                    }
                });
        });
    }

    /**
     * Hard ceiling for hierarchy depth. Hierarchies deeper than this are
     * treated as corrupt (or cyclic) rather than recursed into.
     */
    public const int MAX_HIERARCHY_DEPTH = 100;

    /**
     * Stashed by the updating hook so the saved hook can rewrite
     * descendant paths without recursion.
     */
    public ?string $previousPathForRebuild = null;

    public ?int $previousDepthForRebuild = null;

    /**
     * Update path and depth from the current parent.
     */
    protected function updatePathAndDepth(): void
    {
        if ($this->parent_id === null) {
            // HasUuids assigns the key in its own creating listener, which
            // runs before this one. If the key is somehow still missing,
            // the created hook backfills the root path instead of writing
            // a placeholder that would leak into descendant paths.
            $this->path = $this->getKey();
            $this->depth = 0;

            return;
        }

        $parent = InventoryOwnerScope::applyToLocationQuery(self::query())
            ->whereKey($this->parent_id)
            ->first();

        if ($parent === null) {
            throw new AuthorizationException('Invalid parent location for the current owner context.');
        }

        $this->assertNoHierarchyCycle($parent);

        $depth = $parent->depth + 1;

        if ($depth > self::MAX_HIERARCHY_DEPTH) {
            throw new InvalidArgumentException('Location hierarchy exceeds the maximum supported depth.');
        }

        $parentPath = $parent->path ?? $parent->getKey();

        $this->path = $parentPath . '/' . $this->getKey();
        $this->depth = $depth;
    }

    /**
     * Walk the ancestor chain to reject self-parenting and cycles.
     */
    private function assertNoHierarchyCycle(InventoryLocation $parent): void
    {
        $seen = [$this->getKey()];
        $current = $parent;
        $hops = 0;

        while ($current !== null && $hops <= self::MAX_HIERARCHY_DEPTH) {
            if (in_array($current->getKey(), $seen, true)) {
                throw new InvalidArgumentException('Location hierarchy must not contain a cycle.');
            }

            $seen[] = $current->getKey();
            $hops++;

            if ($current->parent_id === null) {
                return;
            }

            $current = InventoryOwnerScope::applyToLocationQuery(self::query())
                ->whereKey($current->parent_id)
                ->first();
        }

        if ($hops > self::MAX_HIERARCHY_DEPTH) {
            throw new InvalidArgumentException('Location hierarchy exceeds the maximum supported depth.');
        }
    }

    private function rememberPreviousPathForRebuild(): void
    {
        $previousPath = $this->getOriginal('path');
        $previousDepth = $this->getOriginal('depth');

        $this->previousPathForRebuild = is_string($previousPath) ? $previousPath : null;
        $this->previousDepthForRebuild = is_int($previousDepth) ? $previousDepth : null;
    }

    /**
     * Rewrite descendant paths after this location moved.
     *
     * Iterative and chunked: every descendant path shares the previous
     * path as a prefix, so each new path is a prefix swap plus a depth
     * delta. Quiet saves keep the rebuild from cascading hooks, and the
     * visited set skips rows caught in legacy cycles.
     */
    protected function rebuildDescendantPaths(string $previousPath, int $previousDepth): void
    {
        if ($this->path === null || $previousPath === '') {
            return;
        }

        $newPrefix = $this->path . '/';
        $oldPrefix = $previousPath . '/';
        $depthDelta = $this->depth - $previousDepth;
        $visited = [$this->getKey()];

        InventoryOwnerScope::applyToLocationQuery(self::query())
            ->where('path', 'like', $oldPrefix . '%')
            ->chunkById(200, function ($descendants) use ($newPrefix, $oldPrefix, $depthDelta, &$visited): void {
                foreach ($descendants as $descendant) {
                    $key = $descendant->getKey();

                    if (in_array($key, $visited, true)) {
                        continue;
                    }

                    $visited[] = $key;

                    $suffix = mb_substr((string) $descendant->path, mb_strlen($oldPrefix));

                    if ($suffix === '') {
                        continue;
                    }

                    $descendant->forceFill([
                        'path' => $newPrefix . $suffix,
                        'depth' => max(0, $descendant->depth + $depthDelta),
                    ])->saveQuietly();
                }
            });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InventoryLocationFactory
    {
        return InventoryLocationFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_hazmat_certified' => 'boolean',
            'priority' => 'integer',
            'depth' => 'integer',
            'coordinate_x' => 'decimal:2',
            'coordinate_y' => 'decimal:2',
            'coordinate_z' => 'decimal:2',
            'pick_sequence' => 'integer',
            'capacity' => 'integer',
            'current_utilization' => 'integer',
            'metadata' => 'array',
        ];
    }
}
