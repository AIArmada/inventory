<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $reference
 * @property string $carrier_code
 * @property string|null $service_code
 * @property string|null $tracking_number
 * @property string|null $carrier_reference
 * @property ShipmentStatus $status
 * @property array $origin_address
 * @property array $destination_address
 * @property int $package_count
 * @property int $total_weight
 * @property int $declared_value
 * @property string $currency
 * @property int $shipping_cost
 * @property int $insurance_cost
 * @property int|null $cod_amount
 * @property string|null $label_url
 * @property string|null $label_format
 * @property \Illuminate\Support\Carbon|null $shipped_at
 * @property \Illuminate\Support\Carbon|null $estimated_delivery_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $last_tracking_sync
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShipmentItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShipmentEvent> $events
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ShipmentLabel> $labels
 */
class Shipment extends Model
{
    use HasOwner;
    use SoftDeletes;

    protected $table = 'shipments';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'shippable_id',
        'shippable_type',
        'reference',
        'carrier_code',
        'service_code',
        'tracking_number',
        'carrier_reference',
        'status',
        'origin_address',
        'destination_address',
        'package_count',
        'total_weight',
        'declared_value',
        'currency',
        'shipping_cost',
        'insurance_cost',
        'cod_amount',
        'label_url',
        'label_format',
        'shipped_at',
        'estimated_delivery_at',
        'delivered_at',
        'last_tracking_sync',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'package_count' => 1,
        'total_weight' => 0,
        'declared_value' => 0,
        'currency' => 'MYR',
        'shipping_cost' => 0,
        'insurance_cost' => 0,
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    /**
     * @return HasMany<ShipmentItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return HasMany<ShipmentEvent>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at', 'desc');
    }

    /**
     * @return HasMany<ShipmentLabel>
     */
    public function labels(): HasMany
    {
        return $this->hasMany(ShipmentLabel::class);
    }

    /**
     * Polymorphic relationship to the "shippable" (order, cart, etc.)
     */
    public function shippable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─────────────────────────────────────────────────────────────
    // STATUS HELPERS
    // ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isInTransit(): bool
    {
        return $this->status->isInTransit();
    }

    public function isDelivered(): bool
    {
        return $this->status->isDelivered();
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canTransitionTo(ShipmentStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function getLatestEvent(): ?ShipmentEvent
    {
        return $this->events()->latest('occurred_at')->first();
    }

    public function isCashOnDelivery(): bool
    {
        return $this->cod_amount !== null && $this->cod_amount > 0;
    }

    // ─────────────────────────────────────────────────────────────
    // ACCESSORS
    // ─────────────────────────────────────────────────────────────

    public function getFormattedShippingCost(): string
    {
        return number_format($this->shipping_cost / 100, 2).' '.$this->currency;
    }

    public function getTotalWeightKg(): float
    {
        return $this->total_weight / 1000;
    }

    protected static function booted(): void
    {
        static::creating(function (Shipment $shipment): void {
            if (empty($shipment->ulid)) {
                $shipment->ulid = (string) Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'origin_address' => 'array',
            'destination_address' => 'array',
            'package_count' => 'integer',
            'total_weight' => 'integer',
            'declared_value' => 'integer',
            'shipping_cost' => 'integer',
            'insurance_cost' => 'integer',
            'cod_amount' => 'integer',
            'shipped_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
            'last_tracking_sync' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
