<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

/**
 * Closure table for efficient ancestor/descendant queries.
 *
 * @property string $id
 * @property string $ancestor_id
 * @property string $descendant_id
 * @property int $depth
 * @property-read Affiliate $ancestor
 * @property-read Affiliate $descendant
 */
class AffiliateNetwork extends Model
{
    use HasUuids;

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    protected $casts = [
        'depth' => 'integer',
    ];

    /**
     * Get all ancestors of an affiliate (upline).
     *
     * @return Collection<int, Affiliate>
     */
    public static function getAncestors(Affiliate $affiliate): Collection
    {
        /** @var Collection<int, self> $paths */
        $paths = static::query()
            ->where('descendant_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->orderBy('depth')
            ->with('ancestor')
            ->get();

        return $paths
            ->map(function (self $path): ?Affiliate {
                $ancestor = $path->ancestor;

                if (! $ancestor) {
                    return null;
                }

                $ancestor->setRelation('pivot', new Pivot(['depth' => $path->depth]));

                return $ancestor;
            })
            ->filter(fn (?Affiliate $ancestor) => $ancestor !== null)
            ->values();
    }

    /**
     * Get all descendants of an affiliate (downline).
     *
     * @return Collection<int, Affiliate>
     */
    public static function getDescendants(Affiliate $affiliate): Collection
    {
        /** @var Collection<int, self> $paths */
        $paths = static::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->orderBy('depth')
            ->with('descendant')
            ->get();

        return $paths
            ->map(function (self $path): ?Affiliate {
                $descendant = $path->descendant;

                if (! $descendant) {
                    return null;
                }

                $descendant->setRelation('pivot', new Pivot(['depth' => $path->depth]));

                return $descendant;
            })
            ->filter(fn (?Affiliate $descendant) => $descendant !== null)
            ->values();
    }

    /**
     * Get descendants at a specific depth.
     *
     * @return Collection<int, Affiliate>
     */
    public static function getAtDepth(Affiliate $affiliate, int $depth): Collection
    {
        /** @var Collection<int, self> $paths */
        $paths = static::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', $depth)
            ->with('descendant')
            ->get();

        return $paths
            ->map(function (self $path): ?Affiliate {
                $descendant = $path->descendant;

                if (! $descendant) {
                    return null;
                }

                $descendant->setRelation('pivot', new Pivot(['depth' => $path->depth]));

                return $descendant;
            })
            ->filter(fn (?Affiliate $descendant) => $descendant !== null)
            ->values();
    }

    /**
     * Get direct children (depth = 1).
     *
     * @return Collection<int, Affiliate>
     */
    public static function getDirectChildren(Affiliate $affiliate): Collection
    {
        return static::getAtDepth($affiliate, 1);
    }

    /**
     * Get the total count of descendants.
     */
    public static function getDescendantCount(Affiliate $affiliate): int
    {
        return static::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->count();
    }

    /**
     * Add an affiliate to the network under a sponsor.
     */
    public static function addToNetwork(Affiliate $affiliate, ?Affiliate $sponsor = null): void
    {
        // Self-referencing entry (every node points to itself at depth 0)
        static::updateOrCreate(
            [
                'ancestor_id' => $affiliate->getKey(),
                'descendant_id' => $affiliate->getKey(),
            ],
            [
                'depth' => 0,
            ]
        );

        if (! $sponsor) {
            return;
        }

        // Copy all ancestor paths from sponsor and add 1 to depth
        $sponsorAncestors = static::query()
            ->where('descendant_id', $sponsor->getKey())
            ->get();

        foreach ($sponsorAncestors as $path) {
            static::updateOrCreate(
                [
                    'ancestor_id' => $path->ancestor_id,
                    'descendant_id' => $affiliate->getKey(),
                ],
                [
                    'depth' => $path->depth + 1,
                ]
            );
        }
    }

    /**
     * Remove an affiliate from the network (and all descendants).
     */
    public static function removeFromNetwork(Affiliate $affiliate): void
    {
        // Get all descendants
        $descendantIds = static::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->pluck('descendant_id');

        // Delete all paths involving this affiliate or its descendants
        static::query()
            ->whereIn('descendant_id', $descendantIds)
            ->delete();
    }

    /**
     * Move an affiliate to a new sponsor.
     */
    public static function moveToNewSponsor(Affiliate $affiliate, Affiliate $newSponsor): void
    {
        // Get all descendants of the affiliate
        $descendantIds = static::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->pluck('descendant_id');

        // Delete all paths that go through old ancestors (excluding self-references)
        static::query()
            ->whereIn('descendant_id', $descendantIds)
            ->where('ancestor_id', '!=', $affiliate->getKey())
            ->whereNotIn('ancestor_id', $descendantIds)
            ->delete();

        // Add new paths from new sponsor's ancestors
        $newSponsorAncestors = static::query()
            ->where('descendant_id', $newSponsor->getKey())
            ->get();

        foreach ($descendantIds as $descendantId) {
            $currentDepth = static::query()
                ->where('ancestor_id', $affiliate->getKey())
                ->where('descendant_id', $descendantId)
                ->value('depth');

            foreach ($newSponsorAncestors as $path) {
                static::updateOrCreate(
                    [
                        'ancestor_id' => $path->ancestor_id,
                        'descendant_id' => $descendantId,
                    ],
                    [
                        'depth' => $path->depth + 1 + $currentDepth,
                    ]
                );
            }
        }
    }

    public function getTable(): string
    {
        return config('affiliates.table_names.network', 'affiliate_network');
    }

    /**
     * @return BelongsTo<Affiliate, self>
     */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'ancestor_id');
    }

    /**
     * @return BelongsTo<Affiliate, self>
     */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'descendant_id');
    }
}
