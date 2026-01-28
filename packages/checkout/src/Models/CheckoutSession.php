<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Models;

use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\States\CheckoutState;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $cart_id
 * @property string|null $customer_id
 * @property string|null $order_id
 * @property string|null $payment_id
 * @property CheckoutState $status
 * @property string|null $current_step
 * @property array<string, mixed> $cart_snapshot
 * @property array<string, string> $step_states
 * @property array<string, mixed> $shipping_data
 * @property array<string, mixed> $billing_data
 * @property array<string, mixed> $pricing_data
 * @property array<string, mixed> $discount_data
 * @property array<string, mixed> $tax_data
 * @property array<string, mixed> $payment_data
 * @property string|null $payment_redirect_url
 * @property int $payment_attempts
 * @property string|null $selected_shipping_method
 * @property string|null $selected_payment_gateway
 * @property int $subtotal
 * @property int $discount_total
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $grand_total
 * @property string $currency
 * @property string|null $error_message
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class CheckoutSession extends Model
{
    use HasOwner;
    use HasStates;
    use HasUuids;

    protected $fillable = [
        'cart_id',
        'customer_id',
        'order_id',
        'payment_id',
        'status',
        'current_step',
        'cart_snapshot',
        'step_states',
        'shipping_data',
        'billing_data',
        'pricing_data',
        'discount_data',
        'tax_data',
        'payment_data',
        'payment_redirect_url',
        'payment_attempts',
        'selected_shipping_method',
        'selected_payment_gateway',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'currency',
        'error_message',
        'expires_at',
        'completed_at',
        'owner_type',
        'owner_id',
    ];

    protected $attributes = [
        'status' => 'pending',
        'payment_attempts' => 0,
        'subtotal' => 0,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('checkout.database.tables', []);
        $prefix = config('checkout.database.table_prefix', '');

        return $tables['checkout_sessions'] ?? $prefix . 'checkout_sessions';
    }

    /**
     * @return BelongsTo<\AIArmada\Customers\Models\Customer, $this>
     */
    public function customer(): BelongsTo
    {
        /** @var class-string<\AIArmada\Customers\Models\Customer> $customerModel */
        $customerModel = config('checkout.models.customer', \AIArmada\Customers\Models\Customer::class);

        return $this->belongsTo($customerModel, 'customer_id');
    }

    /**
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function order(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $orderModel */
        $orderModel = config('checkout.models.order', \AIArmada\Orders\Models\Order::class);

        return $this->belongsTo($orderModel, 'order_id');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function getStepState(string $identifier): ?StepStatus
    {
        $states = $this->step_states ?? [];
        $state = $states[$identifier] ?? null;

        return $state !== null ? StepStatus::from($state) : null;
    }

    public function setStepState(string $identifier, StepStatus $status): void
    {
        $states = $this->step_states ?? [];
        $states[$identifier] = $status->value;

        $this->update(['step_states' => $states]);
    }

    public function isStepCompleted(string $identifier): bool
    {
        $state = $this->getStepState($identifier);

        return $state === StepStatus::Completed || $state === StepStatus::Skipped;
    }

    public function calculateTotals(): void
    {
        $this->grand_total = $this->subtotal
            - $this->discount_total
            + $this->shipping_total
            + $this->tax_total;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        $jsonType = config('checkout.database.json_column_type', 'json') === 'jsonb' ? 'array' : 'array';

        return [
            'status' => CheckoutState::class,
            'cart_snapshot' => $jsonType,
            'step_states' => $jsonType,
            'shipping_data' => $jsonType,
            'billing_data' => $jsonType,
            'pricing_data' => $jsonType,
            'discount_data' => $jsonType,
            'tax_data' => $jsonType,
            'payment_data' => $jsonType,
            'payment_attempts' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CheckoutSession $session): void {
            $session->currency ??= config('checkout.defaults.currency', 'MYR');
        });

        static::updating(function (CheckoutSession $session): void {
            if ($session->isDirty('status') && $session->status instanceof \AIArmada\Checkout\States\Completed) {
                $session->completed_at = CarbonImmutable::now();
            }
        });
    }
}
