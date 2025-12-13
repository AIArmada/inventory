# Spatie Integration Tasks

> **Created:** December 2024  
> **Status:** Active  
> **Reference:** [20-implementation-roadmap.md](20-implementation-roadmap.md) | [PROGRESS.md](PROGRESS.md)

---

## Quick Links

- [Phase 0: Foundation](#phase-0-foundation)
- [Phase 1: Orders State Machine](#phase-1-orders-state-machine)
- [Phase 2: Webhook Unification](#phase-2-webhook-unification)
- [Phase 3: Products Media](#phase-3-products-media)
- [Phase 4: Shipping State Machine](#phase-4-shipping-state-machine)
- [Phase 5: Customer Segmentation](#phase-5-customer-segmentation)
- [Phase 6: Pricing & Tax Settings](#phase-6-pricing--tax-settings)
- [Phase 7: Affiliate States & Tags](#phase-7-affiliate-states--tags)
- [Phase 8: Health Checks](#phase-8-health-checks)

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ⏳ | Not Started |
| 🔄 | In Progress |
| ✅ | Completed |
| ❌ | Blocked |
| 🔶 | Needs Review |

---

## Phase 0: Foundation

**Package:** `commerce-support`  
**Priority:** P0 - Critical  
**Duration:** Week 1-2

### 0.1 Add Core Dependencies

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 0.1.1 | Add `spatie/laravel-activitylog:^4.8` to composer.json | ⏳ | - | |
| 0.1.2 | Add `spatie/laravel-webhook-client:^3.4` to composer.json | ⏳ | - | |
| 0.1.3 | Add `spatie/laravel-settings:^3.3` to composer.json | ⏳ | - | |
| 0.1.4 | Add `spatie/laravel-health:^1.29` to composer.json | ⏳ | - | |
| 0.1.5 | Add `owen-it/laravel-auditing:^13.0` to composer.json | ⏳ | - | |
| 0.1.6 | Run `composer update` and verify installation | ⏳ | - | |

### 0.2 Create LogsCommerceActivity Trait

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 0.2.1 | Create `src/Concerns/LogsCommerceActivity.php` | ⏳ | - | |
| 0.2.2 | Implement `getActivitylogOptions()` method | ⏳ | - | |
| 0.2.3 | Implement `getLoggableAttributes()` method | ⏳ | - | |
| 0.2.4 | Implement `getActivityLogName()` method | ⏳ | - | |
| 0.2.5 | Write unit tests for trait | ⏳ | - | Min 5 tests |

### 0.3 Create HasCommerceAudit Trait

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 0.3.1 | Create `src/Concerns/HasCommerceAudit.php` | ⏳ | - | Uses owen-it |
| 0.3.2 | Implement `transformAudit()` method | ⏳ | - | |
| 0.3.3 | Implement `getAuditInclude()` method | ⏳ | - | |
| 0.3.4 | Implement PII redaction | ⏳ | - | |
| 0.3.5 | Write unit tests for trait | ⏳ | - | Min 5 tests |

### 0.4 Publish Migrations

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 0.4.1 | Publish activitylog migrations | ⏳ | - | |
| 0.4.2 | Publish webhook-client migrations | ⏳ | - | |
| 0.4.3 | Publish settings migrations | ⏳ | - | |
| 0.4.4 | Publish auditing migrations | ⏳ | - | |
| 0.4.5 | Run migrations and verify tables | ⏳ | - | |

### 0.5 Create Shared Contracts

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 0.5.1 | Create `Contracts/Loggable.php` interface | ⏳ | - | |
| 0.5.2 | Create `Contracts/Auditable.php` interface | ⏳ | - | |
| 0.5.3 | Create `Contracts/HasHealthCheck.php` interface | ⏳ | - | |

### 0.6 Documentation

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 0.6.1 | Update commerce-support README | ⏳ | - | |
| 0.6.2 | Document trait usage | ⏳ | - | |
| 0.6.3 | Add examples to docs | ⏳ | - | |

---

## Phase 1: Orders State Machine

**Package:** `orders`  
**Priority:** P0 - Critical  
**Duration:** Week 3-4  
**Depends On:** Phase 0

### 1.1 Add Dependency

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 1.1.1 | Add `spatie/laravel-model-states:^2.7` to orders/composer.json | ⏳ | - | |
| 1.1.2 | Run `composer update` | ⏳ | - | |

### 1.2 Create State Classes

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 1.2.1 | Create OrderState abstract | `src/States/OrderState.php` | ⏳ | - |
| 1.2.2 | Create Pending state | `src/States/Pending.php` | ⏳ | - |
| 1.2.3 | Create Confirmed state | `src/States/Confirmed.php` | ⏳ | - |
| 1.2.4 | Create Processing state | `src/States/Processing.php` | ⏳ | - |
| 1.2.5 | Create ReadyToShip state | `src/States/ReadyToShip.php` | ⏳ | - |
| 1.2.6 | Create Shipped state | `src/States/Shipped.php` | ⏳ | - |
| 1.2.7 | Create Delivered state | `src/States/Delivered.php` | ⏳ | - |
| 1.2.8 | Create Completed state | `src/States/Completed.php` | ⏳ | - |
| 1.2.9 | Create Canceled state | `src/States/Canceled.php` | ⏳ | - |
| 1.2.10 | Create Refunded state | `src/States/Refunded.php` | ⏳ | - |
| 1.2.11 | Create Failed state | `src/States/Failed.php` | ⏳ | - |

### 1.3 Create Transition Classes

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 1.3.1 | Create TransitionToConfirmed | `src/States/Transitions/TransitionToConfirmed.php` | ⏳ | - |
| 1.3.2 | Create TransitionToProcessing | `src/States/Transitions/TransitionToProcessing.php` | ⏳ | - |
| 1.3.3 | Create TransitionToShipped | `src/States/Transitions/TransitionToShipped.php` | ⏳ | - |
| 1.3.4 | Create TransitionToDelivered | `src/States/Transitions/TransitionToDelivered.php` | ⏳ | - |
| 1.3.5 | Create TransitionToCanceled | `src/States/Transitions/TransitionToCanceled.php` | ⏳ | - |
| 1.3.6 | Create TransitionToRefunded | `src/States/Transitions/TransitionToRefunded.php` | ⏳ | - |

### 1.4 Update Order Model

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 1.4.1 | Add `use HasStates` trait to Order | ⏳ | - | |
| 1.4.2 | Add `use LogsCommerceActivity` trait to Order | ⏳ | - | |
| 1.4.3 | Configure state cast in `$casts` | ⏳ | - | |
| 1.4.4 | Register state config in `registerStates()` | ⏳ | - | |

### 1.5 Testing

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 1.5.1 | Write state transition tests | ⏳ | - | Min 10 tests |
| 1.5.2 | Write invalid transition tests | ⏳ | - | Min 5 tests |
| 1.5.3 | Write activity logging tests | ⏳ | - | Min 5 tests |
| 1.5.4 | Run PHPStan on orders package | ⏳ | - | Level 6 |

---

## Phase 2: Webhook Unification

**Package:** `chip`, `jnt`  
**Priority:** P0 - Critical  
**Duration:** Week 5-6  
**Depends On:** Phase 0

### 2.1 CHIP Webhook Handler

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 2.1.1 | Create ChipSignatureValidator | `chip/src/Webhooks/ChipSignatureValidator.php` | ⏳ | - |
| 2.1.2 | Create ChipWebhookProfile | `chip/src/Webhooks/ChipWebhookProfile.php` | ⏳ | - |
| 2.1.3 | Create ProcessChipWebhook job | `chip/src/Webhooks/ProcessChipWebhook.php` | ⏳ | - |
| 2.1.4 | Register webhook route | `chip/routes/webhooks.php` | ⏳ | - |
| 2.1.5 | Update chip config for webhooks | `chip/config/chip.php` | ⏳ | - |

### 2.2 J&T Webhook Handler

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 2.2.1 | Create JntSignatureValidator | `jnt/src/Webhooks/JntSignatureValidator.php` | ⏳ | - |
| 2.2.2 | Create ProcessJntWebhook job | `jnt/src/Webhooks/ProcessJntWebhook.php` | ⏳ | - |
| 2.2.3 | Register webhook route | `jnt/routes/webhooks.php` | ⏳ | - |
| 2.2.4 | Update jnt config for webhooks | `jnt/config/jnt.php` | ⏳ | - |

### 2.3 Cleanup & Testing

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 2.3.1 | Remove old CHIP webhook controller | ⏳ | - | Backup first |
| 2.3.2 | Remove old J&T webhook controller | ⏳ | - | Backup first |
| 2.3.3 | Write signature validation tests | ⏳ | - | |
| 2.3.4 | Write webhook processing tests | ⏳ | - | |
| 2.3.5 | Run PHPStan on chip package | ⏳ | - | Level 6 |
| 2.3.6 | Run PHPStan on jnt package | ⏳ | - | Level 6 |

---

## Phase 3: Products Media

**Package:** `products`  
**Priority:** P1 - High  
**Duration:** Week 7-8  
**Depends On:** Phase 0

### 3.1 Setup

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 3.1.1 | Verify spatie/laravel-medialibrary is installed | ⏳ | - | May already exist |
| 3.1.2 | Publish medialibrary migrations | ⏳ | - | |
| 3.1.3 | Run migrations | ⏳ | - | |

### 3.2 Product Model

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 3.2.1 | Add `implements HasMedia` to Product | ⏳ | - | |
| 3.2.2 | Add `use InteractsWithMedia` trait | ⏳ | - | |
| 3.2.3 | Implement `registerMediaCollections()` | ⏳ | - | gallery, downloads |
| 3.2.4 | Implement `registerMediaConversions()` | ⏳ | - | thumb, preview |

### 3.3 ProductVariant Model

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 3.3.1 | Add `implements HasMedia` to ProductVariant | ⏳ | - | |
| 3.3.2 | Add `use InteractsWithMedia` trait | ⏳ | - | |
| 3.3.3 | Implement `registerMediaCollections()` | ⏳ | - | |

### 3.4 Filament Integration

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 3.4.1 | Add SpatieMediaLibraryFileUpload to ProductResource | ⏳ | - | |
| 3.4.2 | Add media gallery component | ⏳ | - | |
| 3.4.3 | Configure media display in table | ⏳ | - | |

### 3.5 Testing

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 3.5.1 | Write media upload tests | ⏳ | - | |
| 3.5.2 | Write media conversion tests | ⏳ | - | |
| 3.5.3 | Write media deletion tests | ⏳ | - | |

---

## Phase 4: Shipping State Machine

**Package:** `shipping`  
**Priority:** P1 - High  
**Duration:** Week 9-10  
**Depends On:** Phase 0, Phase 1

### 4.1 Add Dependency

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 4.1.1 | Add `spatie/laravel-model-states:^2.7` to shipping/composer.json | ⏳ | - | |

### 4.2 Create State Classes

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 4.2.1 | Create ShipmentState abstract | `src/States/ShipmentState.php` | ⏳ | - |
| 4.2.2 | Create Draft state | `src/States/Draft.php` | ⏳ | - |
| 4.2.3 | Create Pending state | `src/States/Pending.php` | ⏳ | - |
| 4.2.4 | Create LabelGenerated state | `src/States/LabelGenerated.php` | ⏳ | - |
| 4.2.5 | Create PickedUp state | `src/States/PickedUp.php` | ⏳ | - |
| 4.2.6 | Create InTransit state | `src/States/InTransit.php` | ⏳ | - |
| 4.2.7 | Create OutForDelivery state | `src/States/OutForDelivery.php` | ⏳ | - |
| 4.2.8 | Create Delivered state | `src/States/Delivered.php` | ⏳ | - |
| 4.2.9 | Create Failed state | `src/States/Failed.php` | ⏳ | - |
| 4.2.10 | Create ReturnToSender state | `src/States/ReturnToSender.php` | ⏳ | - |
| 4.2.11 | Create Returned state | `src/States/Returned.php` | ⏳ | - |
| 4.2.12 | Create Canceled state | `src/States/Canceled.php` | ⏳ | - |

### 4.3 J&T Webhook Mapping

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 4.3.1 | Map J&T status codes to states | ⏳ | - | |
| 4.3.2 | Create status mapper service | ⏳ | - | |
| 4.3.3 | Integrate with ProcessJntWebhook job | ⏳ | - | |

---

## Phase 5: Customer Segmentation

**Package:** `customers`  
**Priority:** P2 - Medium  
**Duration:** Week 11-12  
**Depends On:** Phase 0

### 5.1 Add Dependencies

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 5.1.1 | Add `spatie/laravel-tags:^4.6` to customers/composer.json | ⏳ | - | |
| 5.1.2 | Add `spatie/laravel-medialibrary:^11.0` to customers/composer.json | ⏳ | - | For avatar |

### 5.2 Update Customer Model

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 5.2.1 | Add `use HasTags` trait | ⏳ | - | |
| 5.2.2 | Add `implements HasMedia` | ⏳ | - | |
| 5.2.3 | Add `use InteractsWithMedia` trait | ⏳ | - | |
| 5.2.4 | Configure avatar media collection | ⏳ | - | |

### 5.3 Segmentation Service

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 5.3.1 | Create CustomerSegmentationService | ⏳ | - | |
| 5.3.2 | Implement VIP tagging logic | ⏳ | - | |
| 5.3.3 | Implement churn risk tagging | ⏳ | - | |
| 5.3.4 | Create auto-tagging command | ⏳ | - | |

---

## Phase 6: Pricing & Tax Settings

**Package:** `pricing`, `tax`  
**Priority:** P2 - Medium  
**Duration:** Week 13-14  
**Depends On:** Phase 0

### 6.1 Pricing Settings

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 6.1.1 | Create PricingSettings | `pricing/src/Settings/PricingSettings.php` | ⏳ | - |
| 6.1.2 | Create PromotionalPricingSettings | `pricing/src/Settings/PromotionalPricingSettings.php` | ⏳ | - |
| 6.1.3 | Create settings migration | `pricing/database/settings/` | ⏳ | - |

### 6.2 Tax Settings

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 6.2.1 | Create TaxSettings | `tax/src/Settings/TaxSettings.php` | ⏳ | - |
| 6.2.2 | Create TaxZoneSettings | `tax/src/Settings/TaxZoneSettings.php` | ⏳ | - |
| 6.2.3 | Create settings migration | `tax/database/settings/` | ⏳ | - |

### 6.3 Filament Pages

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 6.3.1 | Create PricingSettingsPage | ⏳ | - | |
| 6.3.2 | Create TaxSettingsPage | ⏳ | - | |

---

## Phase 7: Affiliate States & Tags

**Package:** `affiliates`  
**Priority:** P2 - Medium  
**Duration:** Week 15-16  
**Depends On:** Phase 0, Phase 1

### 7.1 Add Dependencies

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 7.1.1 | Add `spatie/laravel-model-states:^2.7` to affiliates/composer.json | ⏳ | - | |
| 7.1.2 | Add `spatie/laravel-tags:^4.6` to affiliates/composer.json | ⏳ | - | |

### 7.2 Affiliate State Classes

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 7.2.1 | Create AffiliateState abstract | `src/States/AffiliateState.php` | ⏳ | - |
| 7.2.2 | Create PendingAffiliate state | `src/States/PendingAffiliate.php` | ⏳ | - |
| 7.2.3 | Create ActiveAffiliate state | `src/States/ActiveAffiliate.php` | ⏳ | - |
| 7.2.4 | Create SuspendedAffiliate state | `src/States/SuspendedAffiliate.php` | ⏳ | - |
| 7.2.5 | Create TerminatedAffiliate state | `src/States/TerminatedAffiliate.php` | ⏳ | - |

### 7.3 Payout State Classes

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 7.3.1 | Create PayoutState abstract | `src/States/PayoutState.php` | ⏳ | - |
| 7.3.2 | Create PendingPayout state | `src/States/PendingPayout.php` | ⏳ | - |
| 7.3.3 | Create ApprovedPayout state | `src/States/ApprovedPayout.php` | ⏳ | - |
| 7.3.4 | Create RejectedPayout state | `src/States/RejectedPayout.php` | ⏳ | - |
| 7.3.5 | Create ProcessingPayout state | `src/States/ProcessingPayout.php` | ⏳ | - |
| 7.3.6 | Create PaidPayout state | `src/States/PaidPayout.php` | ⏳ | - |
| 7.3.7 | Create FailedPayout state | `src/States/FailedPayout.php` | ⏳ | - |

---

## Phase 8: Health Checks

**Package:** `commerce-support`, `chip`, `jnt`, `inventory`, `orders`  
**Priority:** P3 - Low  
**Duration:** Week 17  
**Depends On:** Phase 0, Phase 2

### 8.1 Create Health Check Classes

| Task ID | Task | File Path | Status | Assignee |
|---------|------|-----------|--------|----------|
| 8.1.1 | Create ChipGatewayCheck | `chip/src/Health/ChipGatewayCheck.php` | ⏳ | - |
| 8.1.2 | Create JntHealthCheck | `jnt/src/Health/JntHealthCheck.php` | ⏳ | - |
| 8.1.3 | Create LowStockCheck | `inventory/src/Health/LowStockCheck.php` | ⏳ | - |
| 8.1.4 | Create OrderProcessingCheck | `orders/src/Health/OrderProcessingCheck.php` | ⏳ | - |

### 8.2 Register & Configure

| Task ID | Task | Status | Assignee | Notes |
|---------|------|--------|----------|-------|
| 8.2.1 | Register health checks in commerce-support | ⏳ | - | |
| 8.2.2 | Configure health dashboard route | ⏳ | - | |
| 8.2.3 | Configure alert notifications | ⏳ | - | |
| 8.2.4 | Create Filament health widget | ⏳ | - | |

---

## Verification Checklist

### Before Starting Any Phase

- [ ] Read the related documentation in `docs/spatie-integration/`
- [ ] Verify Phase dependencies are complete
- [ ] Create backup of files to be modified (if replacing existing code)
- [ ] Review existing tests that might be affected

### After Completing Any Phase

- [ ] Run package-specific tests: `./vendor/bin/pest tests/src/{Package} --parallel`
- [ ] Run PHPStan: `./vendor/bin/phpstan analyse --level=6 packages/{package}`
- [ ] Run Pint: `./vendor/bin/pint packages/{package}`
- [ ] Update [PROGRESS.md](PROGRESS.md) with completed tasks
- [ ] Add notes for any decisions or issues encountered

---

## Command Reference

```bash
# Add Spatie dependencies (run in package directory)
composer require spatie/laravel-activitylog:^4.8

# Publish migrations
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"

# Run tests for a specific package
./vendor/bin/pest tests/src/CommerceSupport --parallel

# PHPStan for a package
./vendor/bin/phpstan analyse --level=6 packages/commerce-support

# Pint for a package
./vendor/bin/pint packages/commerce-support

# Check existing Spatie usage
grep -r "Spatie" packages/*/src/
```

---

*Last updated: December 2024*
