# Spatie Integration: Implementation Roadmap

> **Document:** Master Implementation Plan  
> **Version:** 1.1  
> **Last Updated:** January 2025  
> **Author:** Visionary Chief Architect  
> **Status:** ✅ Validated against GitHub source code

---

## 🎯 Executive Summary

This document provides a phased implementation roadmap for integrating Spatie packages across the AIArmada Commerce ecosystem. The goal is to:

1. **Reduce development effort** by leveraging battle-tested packages
2. **Increase code quality** through consistent patterns
3. **Enable enterprise features** (audit trails, state machines, media handling)
4. **Maintain package independence** while enabling seamless integration

---

## 📊 Integration Priority Matrix

> **Note:** Priority updated based on January 2025 GitHub source code validation.
> See [13-validation-report.md](13-validation-report.md) for methodology.

| Priority | Package | Effort | Impact | Risk | Status |
|----------|---------|--------|--------|------|--------|
| P0 | laravel-activitylog | Medium | Very High | Low | 📋 Planned |
| P0 | laravel-model-states | Medium | Very High | Medium | ✅ In orders |
| P0 | laravel-webhook-client | Low | High | Low | 📋 Planned |
| P1 | laravel-medialibrary | High | High | Medium | ✅ In products |
| P1 | laravel-settings | Medium | High | Low | 📋 Planned |
| P1 | laravel-tags | Low | Medium | Low | 📋 Planned |
| **P1** | **laravel-query-builder** | **Low** | **High** | **Low** | **📋 Elevated** |
| P2 | laravel-sluggable | Low | Medium | Low | ✅ In products |
| P2 | laravel-translatable | Medium | Medium | Low | 📋 Planned |
| P2 | laravel-health | Low | Medium | Low | 📋 Planned |
| P2 | simple-excel | Low | Medium | Low | 📋 NEW |
| P3 | laravel-model-status | Low | Low | Low | 📋 Optional (history) |
| EVAL | laravel-event-sourcing | High | High | Medium | 🔶 Evaluate for enterprise |
| SKIP | laravel-multitenancy | High | High | High | ⏸️ Not recommended |

---

## 🚀 Phase 0: Foundation (Week 1-2)

### Objective
Establish shared infrastructure in `commerce-support` package.

### Tasks

#### 0.1 Add Core Dependencies

```bash
# In packages/commerce-support
composer require spatie/laravel-activitylog:^4.8 \
                 spatie/laravel-webhook-client:^3.4 \
                 spatie/laravel-settings:^3.3 \
                 spatie/laravel-health:^1.29
```

#### 0.2 Create Shared Trait: LogsCommerceActivity

```php
// packages/commerce-support/src/Concerns/LogsCommerceActivity.php

<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsCommerceActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getLoggableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getActivityLogName());
    }

    protected function getLoggableAttributes(): array
    {
        return $this->fillable ?? [];
    }

    protected function getActivityLogName(): string
    {
        return 'commerce';
    }
}
```

#### 0.3 Publish Migrations

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\WebhookClient\WebhookClientServiceProvider" --tag="webhook-client-migrations"
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"
```

#### 0.4 Run Migrations

```bash
php artisan migrate
```

### Deliverables

- [ ] commerce-support/composer.json updated
- [ ] LogsCommerceActivity trait created
- [ ] Database migrations published and run
- [ ] Basic config files in place
- [ ] Unit tests for trait

### Verification

```bash
# Run commerce-support tests
./vendor/bin/pest tests/src/CommerceSupport --parallel
```

---

## 🚀 Phase 1: Orders State Machine (Week 3-4)

### Objective
Implement complete state machine for orders package.

### Dependencies
- Phase 0 complete
- Orders package exists (even as skeleton)

### Tasks

#### 1.1 Add Dependency to Orders

```bash
# In packages/orders
composer require spatie/laravel-model-states:^2.7
```

#### 1.2 Create State Classes

Create the following files:
- `orders/src/States/OrderState.php` (abstract)
- `orders/src/States/Pending.php`
- `orders/src/States/Confirmed.php`
- `orders/src/States/Processing.php`
- `orders/src/States/ReadyToShip.php`
- `orders/src/States/Shipped.php`
- `orders/src/States/Delivered.php`
- `orders/src/States/Completed.php`
- `orders/src/States/Canceled.php`
- `orders/src/States/Refunded.php`
- `orders/src/States/Failed.php`

#### 1.3 Create Transition Classes

Create the following files:
- `orders/src/States/Transitions/TransitionToConfirmed.php`
- `orders/src/States/Transitions/TransitionToProcessing.php`
- `orders/src/States/Transitions/TransitionToShipped.php`
- `orders/src/States/Transitions/TransitionToDelivered.php`
- `orders/src/States/Transitions/TransitionToCanceled.php`
- `orders/src/States/Transitions/TransitionToRefunded.php`

#### 1.4 Update Order Model

```php
// orders/src/Models/Order.php

use Spatie\ModelStates\HasStates;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Orders\States\OrderState;

class Order extends Model
{
    use HasStates;
    use LogsCommerceActivity;

    protected $casts = [
        'state' => OrderState::class,
    ];
}
```

### Deliverables

- [ ] All 11 state classes created
- [ ] All 6 transition classes created
- [ ] Order model updated with HasStates
- [ ] State transition tests (min 20 tests)
- [ ] Integration with activity logging

### Verification

```bash
# Run orders tests
./vendor/bin/pest tests/src/Orders --parallel

# Verify state transitions
php artisan tinker
>>> $order = \AIArmada\Orders\Models\Order::first()
>>> $order->state->canTransitionTo(\AIArmada\Orders\States\Confirmed::class)
```

---

## 🚀 Phase 2: Webhook Unification (Week 5-6)

### Objective
Migrate CHIP and J&T to unified webhook handling.

### Dependencies
- Phase 0 complete
- CHIP and J&T packages exist

### Tasks

#### 2.1 Create CHIP Webhook Handler

Files to create:
- `chip/src/Webhooks/ChipSignatureValidator.php`
- `chip/src/Webhooks/ChipWebhookProfile.php`
- `chip/src/Webhooks/ProcessChipWebhook.php`

#### 2.2 Create J&T Webhook Handler

Files to create:
- `jnt/src/Webhooks/JntSignatureValidator.php`
- `jnt/src/Webhooks/ProcessJntWebhook.php`

#### 2.3 Configure Webhook Routes

```php
// In both chip and jnt service providers

Route::webhooks('webhooks/chip', 'chip');
Route::webhooks('webhooks/jnt', 'jnt');
```

#### 2.4 Update Config

```php
// config/webhook-client.php

return [
    'configs' => [
        [
            'name' => 'chip',
            'signing_secret' => env('CHIP_WEBHOOK_SECRET'),
            // ... full config
        ],
        [
            'name' => 'jnt',
            'signing_secret' => env('JNT_PRIVATE_KEY'),
            // ... full config
        ],
    ],
];
```

### Deliverables

- [ ] CHIP webhook handler complete
- [ ] J&T webhook handler complete
- [ ] Old webhook code removed
- [ ] Webhook tests (signature, processing)
- [ ] Documentation updated

### Verification

```bash
# Test webhook endpoints
curl -X POST http://localhost/webhooks/chip \
  -H "Content-Type: application/json" \
  -H "X-Signature: test" \
  -d '{"event": "payment.completed"}'
```

---

## 🚀 Phase 3: Products Media (Week 7-8)

### Objective
Implement media library for products package.

### Dependencies
- Phase 0 complete
- Products package exists (even as skeleton)

### Tasks

#### 3.1 Add Dependency

```bash
# In packages/products
composer require spatie/laravel-medialibrary:^11.0
```

#### 3.2 Publish Migration

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

#### 3.3 Create Product Model with Media

```php
// products/src/Models/Product.php

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->useFallbackUrl('/images/placeholder.png');

        $this->addMediaCollection('downloads')
            ->acceptsMimeTypes(['application/pdf', 'application/zip']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200);

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(800);
    }
}
```

### Deliverables

- [ ] Products model with HasMedia
- [ ] ProductVariant model with HasMedia
- [ ] Media conversions configured
- [ ] Upload/delete tests
- [ ] Filament integration for media

### Verification

```php
// In tinker
$product = Product::first();
$product->addMediaFromUrl('https://example.com/image.jpg')
    ->toMediaCollection('gallery');
$product->getFirstMediaUrl('gallery', 'thumb');
```

---

## 🚀 Phase 4: Shipping State Machine (Week 9-10)

### Objective
Implement state machine for shipments.

### Dependencies
- Phase 0 complete
- Phase 1 complete (for pattern reference)
- Shipping package exists

### Tasks

#### 4.1 Add Dependency

```bash
# In packages/shipping
composer require spatie/laravel-model-states:^2.7
```

#### 4.2 Create Shipment States

Files to create (11 states):
- `shipping/src/States/ShipmentState.php`
- `shipping/src/States/Draft.php`
- `shipping/src/States/Pending.php`
- `shipping/src/States/LabelGenerated.php`
- `shipping/src/States/PickedUp.php`
- `shipping/src/States/InTransit.php`
- `shipping/src/States/OutForDelivery.php`
- `shipping/src/States/Delivered.php`
- `shipping/src/States/Failed.php`
- `shipping/src/States/ReturnToSender.php`
- `shipping/src/States/Returned.php`
- `shipping/src/States/Canceled.php`

#### 4.3 Create Transitions

Files to create:
- `shipping/src/States/Transitions/TransitionToPickedUp.php`
- `shipping/src/States/Transitions/TransitionToDelivered.php`
- `shipping/src/States/Transitions/TransitionToFailed.php`

### Deliverables

- [ ] All shipment states created
- [ ] Transition classes created
- [ ] Shipment model updated
- [ ] J&T webhook maps to states
- [ ] State transition tests

---

## 🚀 Phase 5: Customer Segmentation (Week 11-12)

### Objective
Implement tags-based customer segmentation.

### Dependencies
- Phase 0 complete
- Customers package exists

### Tasks

#### 5.1 Add Dependency

```bash
# In packages/customers
composer require spatie/laravel-tags:^4.6 spatie/laravel-medialibrary:^11.0
```

#### 5.2 Update Customer Model

```php
// customers/src/Models/Customer.php

use Spatie\Tags\HasTags;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Customer extends Model implements HasMedia
{
    use HasTags;
    use InteractsWithMedia;

    // ... tag types and methods
}
```

### Deliverables

- [ ] Customer model with HasTags
- [ ] Customer model with HasMedia (avatar)
- [ ] Segmentation service
- [ ] Auto-tagging command
- [ ] Filament segment management

---

## 🚀 Phase 6: Pricing & Tax Settings (Week 13-14)

### Objective
Implement type-safe settings for pricing and tax.

### Dependencies
- Phase 0 complete
- Pricing and tax packages exist

### Tasks

#### 6.1 Create Settings Classes

Files to create:
- `pricing/src/Settings/PricingSettings.php`
- `pricing/src/Settings/PromotionalPricingSettings.php`
- `tax/src/Settings/TaxSettings.php`
- `tax/src/Settings/TaxZoneSettings.php`

#### 6.2 Create Settings Migrations

Files to create:
- `pricing/database/settings/create_pricing_settings.php`
- `tax/database/settings/create_tax_settings.php`

### Deliverables

- [ ] All settings classes created
- [ ] Settings migrations created
- [ ] Services use settings injection
- [ ] Filament settings pages

---

## 🚀 Phase 7: Affiliate States & Tags (Week 15-16)

### Objective
Implement state machines and segmentation for affiliates.

### Dependencies
- Phase 0 complete
- Phase 1 complete (for pattern reference)
- Affiliates package exists

### Tasks

#### 7.1 Add Dependencies

```bash
# In packages/affiliates
composer require spatie/laravel-model-states:^2.7 spatie/laravel-tags:^4.6
```

#### 7.2 Create State Classes

Affiliate states (4):
- `affiliates/src/States/AffiliateState.php`
- `affiliates/src/States/PendingAffiliate.php`
- `affiliates/src/States/ActiveAffiliate.php`
- `affiliates/src/States/SuspendedAffiliate.php`
- `affiliates/src/States/TerminatedAffiliate.php`

Payout states (6):
- `affiliates/src/States/PayoutState.php`
- `affiliates/src/States/PendingPayout.php`
- `affiliates/src/States/ApprovedPayout.php`
- `affiliates/src/States/RejectedPayout.php`
- `affiliates/src/States/ProcessingPayout.php`
- `affiliates/src/States/PaidPayout.php`
- `affiliates/src/States/FailedPayout.php`

### Deliverables

- [ ] Affiliate state machine complete
- [ ] Payout state machine complete
- [ ] Affiliate tagging (tiers, programs)
- [ ] Analytics service with activity log

---

## 🚀 Phase 8: Health Checks (Week 17)

### Objective
Implement comprehensive health checks.

### Dependencies
- Phase 0 complete
- Phase 2 complete (webhook packages)

### Tasks

#### 8.1 Create Health Checks

Files to create:
- `chip/src/Health/ChipGatewayCheck.php`
- `jnt/src/Health/JntHealthCheck.php`
- `inventory/src/Health/LowStockCheck.php`
- `orders/src/Health/OrderProcessingCheck.php`

#### 8.2 Register Health Checks

```php
// In commerce-support service provider

use Spatie\Health\Facades\Health;

Health::checks([
    // Gateway checks
    ChipGatewayCheck::new(),
    JntHealthCheck::new(),
    
    // Business checks
    LowStockCheck::new()->threshold(10),
    OrderProcessingCheck::new()->maxAge(hours: 24),
]);
```

### Deliverables

- [ ] All health checks created
- [ ] Health dashboard route
- [ ] Alert notifications configured
- [ ] Filament health widget

---

## 📈 Success Metrics

### Code Quality Metrics

| Metric | Target | Current |
|--------|--------|---------|
| Test Coverage | 85% | TBD |
| PHPStan Level | 6 | 6 |
| Code Duplication | <5% | TBD |

### Integration Metrics

| Integration | Status | Tests |
|-------------|--------|-------|
| Activity Logging | ❌ | 0 |
| State Machines | ❌ | 0 |
| Webhook Client | ❌ | 0 |
| Media Library | ❌ | 0 |
| Settings | ❌ | 0 |
| Tags | ❌ | 0 |
| Health Checks | ❌ | 0 |

### Performance Metrics

| Operation | Target | Baseline |
|-----------|--------|----------|
| Order State Transition | <50ms | TBD |
| Webhook Processing | <100ms | TBD |
| Media Upload | <2s | TBD |

---

## 🔄 Rollback Procedures

### If Integration Fails

1. **Revert composer.json changes**
   ```bash
   git checkout -- packages/*/composer.json
   composer update
   ```

2. **Revert migrations**
   ```bash
   php artisan migrate:rollback --step=1
   ```

3. **Remove trait usages**
   - Remove `use LogsCommerceActivity;` from models
   - Remove `use HasStates;` from models
   - Remove `use HasTags;` from models

4. **Restore old implementations**
   - Restore custom webhook handlers
   - Restore string-based status fields

---

## 📋 Final Checklist

### Pre-Launch

- [ ] All 8 phases complete
- [ ] Test coverage ≥85%
- [ ] PHPStan passing at level 6
- [ ] Documentation updated
- [ ] Breaking changes documented
- [ ] Migration guide written

### Launch Day

- [ ] Database backup
- [ ] Run migrations
- [ ] Clear all caches
- [ ] Monitor error rates
- [ ] Monitor performance

### Post-Launch

- [ ] Monitor activity log growth
- [ ] Monitor webhook_calls table growth
- [ ] Optimize slow queries
- [ ] Gather team feedback

---

## 🔗 Document Index

| Document | Description |
|----------|-------------|
| [00-overview.md](00-overview.md) | Master integration analysis |
| [01-commerce-support.md](01-commerce-support.md) | Foundation package blueprint |
| [02-products-package.md](02-products-package.md) | Products integration |
| [03-customers-package.md](03-customers-package.md) | Customers integration |
| [04-orders-package.md](04-orders-package.md) | Orders state machine |
| [05-operational-packages.md](05-operational-packages.md) | Cart/Inventory/Vouchers |
| [08-payment-packages.md](08-payment-packages.md) | Payment webhooks |
| [09-shipping-packages.md](09-shipping-packages.md) | Shipping states |
| [10-pricing-tax.md](10-pricing-tax.md) | Pricing & Tax settings |
| [11-affiliates-docs.md](11-affiliates-docs.md) | Affiliates & Docs |

---

*This roadmap was created by the Visionary Chief Architect.*  
*Total estimated effort: 17 weeks*  
*Confidence level: High*
