<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon $date
 * @property string|null $segment
 * @property int $carts_created
 * @property int $carts_active
 * @property int $carts_empty
 * @property int $carts_with_items
 * @property int $checkouts_started
 * @property int $checkouts_completed
 * @property int $checkouts_abandoned
 * @property int $recovery_emails_sent
 * @property int $carts_recovered
 * @property int $recovered_revenue_cents
 * @property int $total_cart_value_cents
 * @property int $average_cart_value_cents
 * @property int $total_items
 * @property float $average_items_per_cart
 * @property int $fraud_alerts_high
 * @property int $fraud_alerts_medium
 * @property int $carts_blocked
 * @property int $collaborative_carts
 * @property int $total_collaborators
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CartDailyMetrics extends Model
{
    use HasUuids;

    protected $fillable = [
        'date',
        'segment',
        'carts_created',
        'carts_active',
        'carts_empty',
        'carts_with_items',
        'checkouts_started',
        'checkouts_completed',
        'checkouts_abandoned',
        'recovery_emails_sent',
        'carts_recovered',
        'recovered_revenue_cents',
        'total_cart_value_cents',
        'average_cart_value_cents',
        'total_items',
        'average_items_per_cart',
        'fraud_alerts_high',
        'fraud_alerts_medium',
        'carts_blocked',
        'collaborative_carts',
        'total_collaborators',
    ];

    public function getTable(): string
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $prefix . 'daily_metrics';
    }

    public function getConversionRate(): float
    {
        if ($this->checkouts_started === 0) {
            return 0.0;
        }

        return $this->checkouts_completed / $this->checkouts_started;
    }

    public function getAbandonmentRate(): float
    {
        if ($this->checkouts_started === 0) {
            return 0.0;
        }

        return $this->checkouts_abandoned / $this->checkouts_started;
    }

    public function getRecoveryRate(): float
    {
        if ($this->checkouts_abandoned === 0) {
            return 0.0;
        }

        return $this->carts_recovered / $this->checkouts_abandoned;
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'carts_created' => 'integer',
            'carts_active' => 'integer',
            'carts_empty' => 'integer',
            'carts_with_items' => 'integer',
            'checkouts_started' => 'integer',
            'checkouts_completed' => 'integer',
            'checkouts_abandoned' => 'integer',
            'recovery_emails_sent' => 'integer',
            'carts_recovered' => 'integer',
            'recovered_revenue_cents' => 'integer',
            'total_cart_value_cents' => 'integer',
            'average_cart_value_cents' => 'integer',
            'total_items' => 'integer',
            'average_items_per_cart' => 'float',
            'fraud_alerts_high' => 'integer',
            'fraud_alerts_medium' => 'integer',
            'carts_blocked' => 'integer',
            'collaborative_carts' => 'integer',
            'total_collaborators' => 'integer',
        ];
    }
}
