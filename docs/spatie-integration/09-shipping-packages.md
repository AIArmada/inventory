# Shipping Packages: Spatie Integration Blueprint

> **Packages:** `aiarmada/shipping`, `aiarmada/jnt`  
> **Status:** Built (Enhanceable)  
> **Role:** Extension Layer - Logistics

---

## 📋 Current State Analysis

### Shipping Package

- Multi-carrier abstraction layer
- Rate calculation engine
- Label generation
- Tracking integration
- Shipment lifecycle management

### J&T Express Package

- Direct J&T API integration
- AWB generation
- Pickup scheduling
- Real-time tracking
- Webhook handling for status updates

---

## 🎯 Critical Integration: laravel-model-states

### Shipment State Machine

Shipments have complex lifecycle that benefits from state machine pattern.

```php
// shipping/src/States/ShipmentState.php

namespace AIArmada\Shipping\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ShipmentState extends State
{
    abstract public function color(): string;
    abstract public function label(): string;
    abstract public function icon(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class, TransitionToPending::class)
            ->allowTransition(Pending::class, LabelGenerated::class, TransitionToLabelGenerated::class)
            ->allowTransition(LabelGenerated::class, PickedUp::class, TransitionToPickedUp::class)
            ->allowTransition(PickedUp::class, InTransit::class, TransitionToInTransit::class)
            ->allowTransition(InTransit::class, OutForDelivery::class)
            ->allowTransition(OutForDelivery::class, Delivered::class, TransitionToDelivered::class)
            ->allowTransition([InTransit::class, OutForDelivery::class], Failed::class, TransitionToFailed::class)
            ->allowTransition(Failed::class, ReturnToSender::class)
            ->allowTransition(ReturnToSender::class, Returned::class)
            ->allowTransition([Pending::class, LabelGenerated::class], Canceled::class);
    }
}
```

### Individual States

```php
// shipping/src/States/Draft.php
namespace AIArmada\Shipping\States;

class Draft extends ShipmentState
{
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Draft';
    }

    public function icon(): string
    {
        return 'heroicon-o-document';
    }
}

// shipping/src/States/Pending.php
class Pending extends ShipmentState
{
    public function color(): string
    {
        return 'yellow';
    }

    public function label(): string
    {
        return 'Pending Pickup';
    }

    public function icon(): string
    {
        return 'heroicon-o-clock';
    }
}

// shipping/src/States/LabelGenerated.php
class LabelGenerated extends ShipmentState
{
    public function color(): string
    {
        return 'blue';
    }

    public function label(): string
    {
        return 'Label Generated';
    }

    public function icon(): string
    {
        return 'heroicon-o-qr-code';
    }
}

// shipping/src/States/PickedUp.php
class PickedUp extends ShipmentState
{
    public function color(): string
    {
        return 'indigo';
    }

    public function label(): string
    {
        return 'Picked Up';
    }

    public function icon(): string
    {
        return 'heroicon-o-truck';
    }
}

// shipping/src/States/InTransit.php
class InTransit extends ShipmentState
{
    public function color(): string
    {
        return 'purple';
    }

    public function label(): string
    {
        return 'In Transit';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-right-circle';
    }
}

// shipping/src/States/OutForDelivery.php
class OutForDelivery extends ShipmentState
{
    public function color(): string
    {
        return 'orange';
    }

    public function label(): string
    {
        return 'Out for Delivery';
    }

    public function icon(): string
    {
        return 'heroicon-o-home';
    }
}

// shipping/src/States/Delivered.php
class Delivered extends ShipmentState
{
    public function color(): string
    {
        return 'green';
    }

    public function label(): string
    {
        return 'Delivered';
    }

    public function icon(): string
    {
        return 'heroicon-o-check-circle';
    }
}

// shipping/src/States/Failed.php
class Failed extends ShipmentState
{
    public function color(): string
    {
        return 'red';
    }

    public function label(): string
    {
        return 'Delivery Failed';
    }

    public function icon(): string
    {
        return 'heroicon-o-x-circle';
    }
}

// shipping/src/States/ReturnToSender.php
class ReturnToSender extends ShipmentState
{
    public function color(): string
    {
        return 'amber';
    }

    public function label(): string
    {
        return 'Returning to Sender';
    }

    public function icon(): string
    {
        return 'heroicon-o-arrow-uturn-left';
    }
}

// shipping/src/States/Returned.php
class Returned extends ShipmentState
{
    public function color(): string
    {
        return 'slate';
    }

    public function label(): string
    {
        return 'Returned';
    }

    public function icon(): string
    {
        return 'heroicon-o-inbox-arrow-down';
    }
}

// shipping/src/States/Canceled.php
class Canceled extends ShipmentState
{
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Canceled';
    }

    public function icon(): string
    {
        return 'heroicon-o-x-mark';
    }
}
```

### Transition Classes

```php
// shipping/src/States/Transitions/TransitionToPickedUp.php

namespace AIArmada\Shipping\States\Transitions;

use Spatie\ModelStates\Transition;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Events\ShipmentPickedUp;
use AIArmada\Shipping\States\PickedUp;

class TransitionToPickedUp extends Transition
{
    public function __construct(
        public Shipment $shipment,
        public ?string $pickupTime = null,
        public ?string $driverName = null,
    ) {}

    public function handle(): Shipment
    {
        $this->shipment->update([
            'state' => PickedUp::class,
            'picked_up_at' => $this->pickupTime ?? now(),
            'driver_name' => $this->driverName,
        ]);

        activity('shipments')
            ->performedOn($this->shipment)
            ->withProperties([
                'picked_up_at' => $this->pickupTime ?? now(),
                'driver' => $this->driverName,
            ])
            ->log('Shipment picked up by carrier');

        event(new ShipmentPickedUp($this->shipment));

        return $this->shipment;
    }
}

// shipping/src/States/Transitions/TransitionToDelivered.php

class TransitionToDelivered extends Transition
{
    public function __construct(
        public Shipment $shipment,
        public ?string $recipientName = null,
        public ?string $signature = null,
        public ?string $photoProof = null,
    ) {}

    public function handle(): Shipment
    {
        $this->shipment->update([
            'state' => Delivered::class,
            'delivered_at' => now(),
            'recipient_name' => $this->recipientName,
            'delivery_signature' => $this->signature,
            'delivery_photo' => $this->photoProof,
        ]);

        // Update related order
        if ($this->shipment->order) {
            $this->shipment->order->markAsDelivered();
        }

        activity('shipments')
            ->performedOn($this->shipment)
            ->withProperties([
                'recipient' => $this->recipientName,
                'has_signature' => !empty($this->signature),
                'has_photo' => !empty($this->photoProof),
            ])
            ->log('Shipment delivered successfully');

        event(new ShipmentDelivered($this->shipment));

        return $this->shipment;
    }
}

// shipping/src/States/Transitions/TransitionToFailed.php

class TransitionToFailed extends Transition
{
    public function __construct(
        public Shipment $shipment,
        public string $reason,
        public ?int $attemptNumber = null,
    ) {}

    public function handle(): Shipment
    {
        $this->shipment->update([
            'state' => Failed::class,
            'failure_reason' => $this->reason,
            'failed_at' => now(),
            'delivery_attempts' => ($this->shipment->delivery_attempts ?? 0) + 1,
        ]);

        // Check if max attempts reached
        $maxAttempts = config('shipping.max_delivery_attempts', 3);
        if ($this->shipment->delivery_attempts >= $maxAttempts) {
            $this->shipment->flagForReturn();
        }

        activity('shipments')
            ->performedOn($this->shipment)
            ->withProperties([
                'reason' => $this->reason,
                'attempt' => $this->shipment->delivery_attempts,
            ])
            ->log("Delivery attempt failed: {$this->reason}");

        event(new ShipmentDeliveryFailed($this->shipment, $this->reason));

        return $this->shipment;
    }
}
```

### Model Integration

```php
// shipping/src/Models/Shipment.php

namespace AIArmada\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;
use AIArmada\Shipping\States\ShipmentState;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;

class Shipment extends Model
{
    use HasStates;
    use LogsCommerceActivity;

    protected $casts = [
        'state' => ShipmentState::class,
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'tracking_events' => 'array',
    ];

    // State transition methods
    public function markAsPickedUp(?string $pickupTime = null, ?string $driver = null): self
    {
        $this->state->transitionTo(
            States\PickedUp::class,
            pickupTime: $pickupTime,
            driverName: $driver,
        );

        return $this;
    }

    public function markAsDelivered(
        ?string $recipientName = null,
        ?string $signature = null,
        ?string $photo = null
    ): self {
        $this->state->transitionTo(
            States\Delivered::class,
            recipientName: $recipientName,
            signature: $signature,
            photoProof: $photo,
        );

        return $this;
    }

    public function markAsFailed(string $reason, ?int $attempt = null): self
    {
        $this->state->transitionTo(
            States\Failed::class,
            reason: $reason,
            attemptNumber: $attempt,
        );

        return $this;
    }

    // State checks
    public function isDelivered(): bool
    {
        return $this->state instanceof States\Delivered;
    }

    public function isInTransit(): bool
    {
        return $this->state instanceof States\InTransit
            || $this->state instanceof States\OutForDelivery;
    }

    public function isCancellable(): bool
    {
        return $this->state->canTransitionTo(States\Canceled::class);
    }

    // Scopes
    public function scopeInTransit($query)
    {
        return $query->whereState('state', [
            States\InTransit::class,
            States\OutForDelivery::class,
        ]);
    }

    public function scopeRequiringAttention($query)
    {
        return $query->whereState('state', [
            States\Failed::class,
            States\ReturnToSender::class,
        ]);
    }
}
```

---

## 🎯 Secondary Integration: laravel-webhook-client

### J&T Webhook Handler

```php
// jnt/src/Webhooks/JntSignatureValidator.php

namespace AIArmada\Jnt\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class JntSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = $request->header('digest');
        $bizContent = $request->input('bizContent');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        if (! is_string($bizContent) || $bizContent === '') {
            return false;
        }

        // J&T webhook signature is a base64(md5(bizContent + secret, raw=true)) digest.
        $expectedSignature = base64_encode(md5($bizContent . $config->signingSecret, true));

        return hash_equals($expectedSignature, $signature);
    }
}
```

```php
// jnt/src/Webhooks/ProcessJntWebhook.php

namespace AIArmada\Jnt\Webhooks;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use AIArmada\Shipping\Models\Shipment;

class ProcessJntWebhook extends ProcessWebhookJob
{
    protected array $stateMap = [
        'PICKED_UP' => \AIArmada\Shipping\States\PickedUp::class,
        'IN_TRANSIT' => \AIArmada\Shipping\States\InTransit::class,
        'OUT_FOR_DELIVERY' => \AIArmada\Shipping\States\OutForDelivery::class,
        'DELIVERED' => \AIArmada\Shipping\States\Delivered::class,
        'DELIVERY_FAILED' => \AIArmada\Shipping\States\Failed::class,
        'RETURNING' => \AIArmada\Shipping\States\ReturnToSender::class,
        'RETURNED' => \AIArmada\Shipping\States\Returned::class,
    ];

    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $awb = $payload['awb'] ?? $payload['tracking_number'];
        $status = $payload['status'] ?? $payload['event_type'];

        $shipment = Shipment::where('tracking_number', $awb)
            ->orWhere('carrier_reference', $awb)
            ->first();

        if (!$shipment) {
            activity('webhooks')
                ->withProperties(['awb' => $awb, 'status' => $status])
                ->log("J&T webhook received for unknown shipment: {$awb}");
            return;
        }

        // Add tracking event
        $trackingEvents = $shipment->tracking_events ?? [];
        $trackingEvents[] = [
            'timestamp' => $payload['timestamp'] ?? now()->toIso8601String(),
            'status' => $status,
            'location' => $payload['location'] ?? null,
            'description' => $payload['description'] ?? null,
        ];
        $shipment->tracking_events = $trackingEvents;
        $shipment->save();

        // Transition state
        $this->transitionShipmentState($shipment, $status, $payload);

        $this->webhookCall->update(['processed_at' => now()]);
    }

    protected function transitionShipmentState(Shipment $shipment, string $status, array $payload): void
    {
        $targetState = $this->stateMap[$status] ?? null;

        if (!$targetState || !$shipment->state->canTransitionTo($targetState)) {
            return;
        }

        match($status) {
            'PICKED_UP' => $shipment->markAsPickedUp(
                $payload['timestamp'] ?? null,
                $payload['driver_name'] ?? null
            ),
            'DELIVERED' => $shipment->markAsDelivered(
                $payload['recipient_name'] ?? null,
                $payload['signature'] ?? null,
                $payload['photo_url'] ?? null
            ),
            'DELIVERY_FAILED' => $shipment->markAsFailed(
                $payload['failure_reason'] ?? 'Unknown',
                $payload['attempt_number'] ?? null
            ),
            default => $shipment->state->transitionTo($targetState),
        };
    }
}
```

### Webhook Configuration

```php
// config/webhook-client.php - J&T config

[
    'name' => 'jnt',
    'signing_secret' => env('JNT_PRIVATE_KEY'),
    'signature_header_name' => 'digest',
    'signature_validator' => \AIArmada\Jnt\Webhooks\JntSignatureValidator::class,
    'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
    'process_webhook_job' => \AIArmada\Jnt\Webhooks\ProcessJntWebhook::class,
    'store_headers' => ['digest'],
],
```

---

## 📊 Shipment State Diagram

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                      SHIPMENT STATE MACHINE                                   │
├──────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│   ┌───────┐                                                                   │
│   │ Draft │                                                                   │
│   └───┬───┘                                                                   │
│       │ confirmShipment()                                                     │
│       ▼                                                                       │
│   ┌─────────┐  generateLabel()  ┌────────────────┐                           │
│   │ Pending ├───────────────────► Label Generated │                           │
│   └────┬────┘                   └───────┬────────┘                           │
│        │                                │                                     │
│        │ cancel()                       │ markAsPickedUp()                   │
│        ▼                                ▼                                     │
│   ┌──────────┐                    ┌──────────┐                               │
│   │ Canceled │                    │ PickedUp │                               │
│   └──────────┘                    └────┬─────┘                               │
│                                        │                                     │
│                                        │ updateTracking()                    │
│                                        ▼                                     │
│                                  ┌───────────┐                               │
│                                  │ In Transit │                               │
│                                  └─────┬─────┘                               │
│                                        │                                     │
│                       ┌────────────────┼────────────────┐                    │
│                       │                │                │                    │
│                       ▼                ▼                ▼                    │
│              ┌─────────────────┐ ┌──────────┐    ┌──────────┐               │
│              │ Out for Delivery │ │  Failed  │    │Exception │               │
│              └────────┬────────┘ └────┬─────┘    └──────────┘               │
│                       │               │                                      │
│           ┌───────────┼───────────────┘                                      │
│           │           │                                                      │
│           ▼           ▼                                                      │
│     ┌───────────┐  ┌─────────────────┐                                       │
│     │ Delivered │  │ Return to Sender│                                       │
│     └───────────┘  └───────┬─────────┘                                       │
│           ▲                │                                                 │
│           │                ▼                                                 │
│           │          ┌──────────┐                                            │
│           │          │ Returned │                                            │
│           │          └──────────┘                                            │
│           │                                                                  │
│   ✅ SUCCESS         ⚠️ RETURN COMPLETED                                     │
│                                                                               │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Tertiary Integration: laravel-health

### Shipping Carrier Health Checks

```php
// shipping/src/Health/CarrierHealthCheck.php

namespace AIArmada\Shipping\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use AIArmada\Shipping\Contracts\ShippingCarrier;

abstract class CarrierHealthCheck extends Check
{
    abstract protected function getCarrier(): ShippingCarrier;
    abstract protected function getCarrierName(): string;

    public function run(): Result
    {
        $result = Result::make();
        $carrier = $this->getCarrier();
        $carrierName = $this->getCarrierName();

        try {
            if ($carrier->healthCheck()) {
                return $result->ok("{$carrierName} API is operational");
            }
            return $result->warning("{$carrierName} API is degraded");
        } catch (\Exception $e) {
            return $result->failed("{$carrierName} API is unreachable: " . $e->getMessage());
        }
    }
}

// jnt/src/Health/JntHealthCheck.php

namespace AIArmada\Jnt\Health;

use AIArmada\Shipping\Health\CarrierHealthCheck;
use AIArmada\Jnt\JntClient;

class JntHealthCheck extends CarrierHealthCheck
{
    protected function getCarrier(): JntClient
    {
        return app(JntClient::class);
    }

    protected function getCarrierName(): string
    {
        return 'J&T Express';
    }
}
```

---

## 📦 composer.json Updates

### shipping/composer.json

```json
{
    "name": "aiarmada/shipping",
    "require": {
        "php": "^8.4",
        "aiarmada/commerce-support": "^1.0",
        "spatie/laravel-model-states": "^2.7"
    }
}
```

### jnt/composer.json

```json
{
    "name": "aiarmada/jnt",
    "require": {
        "php": "^8.4",
        "aiarmada/shipping": "^1.0"
    }
}
```

---

## ✅ Implementation Checklist

### Phase 1: Shipment State Machine

- [ ] Create ShipmentState abstract class
- [ ] Create all state classes (11 states)
- [ ] Create transition classes
- [ ] Add HasStates to Shipment model
- [ ] Write tests for all transitions
- [ ] Document allowed transitions

### Phase 2: Webhook Integration

- [ ] Create JntSignatureValidator
- [ ] Create ProcessJntWebhook job
- [ ] Configure webhook-client for J&T
- [ ] Map J&T statuses to Shipment states
- [ ] Test webhook processing
- [ ] Remove old webhook handler

### Phase 3: Health Checks

- [ ] Create abstract CarrierHealthCheck
- [ ] Create JntHealthCheck
- [ ] Register health checks
- [ ] Add monitoring alerts

### Phase 4: Multi-Carrier Support

- [ ] Create base webhook processor interface
- [ ] Document carrier integration pattern
- [ ] Prepare for additional carriers

---

## 🔗 Related Documents

- [00-overview.md](00-overview.md) - Master overview
- [04-orders-package.md](04-orders-package.md) - Order fulfillment states
- [08-payment-packages.md](08-payment-packages.md) - Webhook patterns

---

*This blueprint was created by the Visionary Chief Architect.*
