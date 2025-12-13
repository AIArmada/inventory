---
title: Phase 2 - Recovery System
---

# Phase 2: Advanced Recovery System

> **Status:** Not Started  
> **Priority:** High  
> **Estimated Effort:** 1 Sprint

---

## Overview

Transform the recovery optimizer widget into a full recovery campaign management system with automation, templates, and performance tracking.

---

## Components

### 1. RecoveryCampaign Model

Campaign management for organized recovery efforts.

```php
// Migration: create_cart_recovery_campaigns_table
Schema::create('cart_recovery_campaigns', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('status')->default('draft'); // draft, active, paused, completed
    
    // Targeting
    $table->unsignedBigInteger('min_cart_value_cents')->nullable();
    $table->unsignedBigInteger('max_cart_value_cents')->nullable();
    $table->unsignedInteger('min_items')->nullable();
    $table->unsignedInteger('max_items')->nullable();
    $table->unsignedInteger('abandonment_age_hours_min')->default(1);
    $table->unsignedInteger('abandonment_age_hours_max')->default(168); // 7 days
    $table->json('customer_segments')->nullable();
    
    // Strategy
    $table->string('strategy'); // discount, free_shipping, reminder, personalized
    $table->unsignedInteger('discount_percent')->nullable();
    $table->string('discount_code')->nullable();
    $table->unsignedInteger('max_attempts')->default(3);
    $table->unsignedInteger('delay_between_attempts_hours')->default(24);
    
    // Schedule
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->json('send_times')->nullable(); // Preferred send times
    
    // Metrics
    $table->unsignedInteger('emails_sent')->default(0);
    $table->unsignedInteger('emails_opened')->default(0);
    $table->unsignedInteger('clicks')->default(0);
    $table->unsignedInteger('recoveries')->default(0);
    $table->unsignedBigInteger('recovered_revenue_cents')->default(0);
    
    $table->timestamps();
    $table->softDeletes();
});
```

### 2. RecoveryAttempt Model

Track individual recovery attempts for each cart.

```php
// Migration: create_cart_recovery_attempts_table
Schema::create('cart_recovery_attempts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('campaign_id');
    $table->foreignUuid('cart_id');
    
    $table->unsignedInteger('attempt_number');
    $table->string('channel'); // email, sms, push
    $table->string('status'); // pending, sent, delivered, opened, clicked, converted, failed
    
    $table->string('strategy');
    $table->unsignedInteger('discount_percent')->nullable();
    $table->string('discount_code')->nullable();
    
    $table->timestamp('scheduled_at');
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('opened_at')->nullable();
    $table->timestamp('clicked_at')->nullable();
    $table->timestamp('converted_at')->nullable();
    
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->unique(['campaign_id', 'cart_id', 'attempt_number']);
});
```

### 3. RecoveryTemplate Model

Email/SMS templates for recovery messages.

```php
// Migration: create_cart_recovery_templates_table
Schema::create('cart_recovery_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('channel'); // email, sms
    $table->string('strategy'); // discount, free_shipping, reminder
    
    $table->string('subject')->nullable(); // For email
    $table->text('body');
    $table->text('html_body')->nullable(); // For email
    
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    
    $table->timestamps();
});
```

### 4. RecoveryScheduler Service

Automated recovery campaign execution.

```php
class RecoveryScheduler
{
    public function scheduleForCampaign(RecoveryCampaign $campaign): int;
    public function processScheduledAttempts(): int;
    public function getNextAttempts(int $limit = 100): Collection;
    public function pauseCampaign(RecoveryCampaign $campaign): void;
    public function resumeCampaign(RecoveryCampaign $campaign): void;
}
```

### 5. RecoveryDispatcher Service

Execute recovery actions across channels.

```php
class RecoveryDispatcher
{
    public function dispatch(RecoveryAttempt $attempt): bool;
    public function dispatchEmail(RecoveryAttempt $attempt): bool;
    public function dispatchSMS(RecoveryAttempt $attempt): bool;
    public function recordDelivery(RecoveryAttempt $attempt): void;
    public function recordOpen(RecoveryAttempt $attempt): void;
    public function recordClick(RecoveryAttempt $attempt): void;
    public function recordConversion(RecoveryAttempt $attempt): void;
}
```

### 6. RecoveryAnalytics Service

Analytics for recovery campaigns.

```php
class RecoveryAnalytics
{
    public function getCampaignMetrics(RecoveryCampaign $campaign): CampaignMetrics;
    public function getOverallMetrics(Carbon $from, Carbon $to): OverallMetrics;
    public function getStrategyComparison(): array;
    public function getOptimalSendTimes(): array;
    public function getABTestResults(string $testId): ABTestResults;
}
```

---

## DTOs

```php
// CampaignMetrics
class CampaignMetrics extends Data
{
    public int $total_targeted;
    public int $emails_sent;
    public int $emails_opened;
    public int $clicks;
    public int $conversions;
    public int $recovered_revenue_cents;
    public float $open_rate;
    public float $click_rate;
    public float $conversion_rate;
    public float $roi;
}

// RecoveryInsight
class RecoveryInsight extends Data
{
    public string $insight_type;
    public string $message;
    public ?string $recommendation;
    public array $data;
}
```

---

## Filament Components

### RecoveryCampaignResource

Full resource for campaign management.

```php
class RecoveryCampaignResource extends Resource
{
    // List with metrics
    // Create/Edit with strategy builder
    // View with performance dashboard
    
    protected static ?string $model = RecoveryCampaign::class;
    protected static ?string $navigationGroup = 'Cart Recovery';
}
```

### RecoveryTemplateResource

Template management resource.

```php
class RecoveryTemplateResource extends Resource
{
    // WYSIWYG editor for email templates
    // Preview functionality
    // Variable interpolation guide
}
```

### RecoverySettingsPage

Configuration page for recovery system.

```php
class RecoverySettingsPage extends Page
{
    // Default strategies
    // Email sender settings
    // SMS provider config
    // Automation toggles
}
```

### New Widgets

1. **CampaignPerformanceWidget** - Active campaign stats
2. **RecoveryFunnelWidget** - Sent → Opened → Clicked → Converted
3. **StrategyComparisonWidget** - Which strategies work best
4. **OptimalTimingWidget** - Best times to send recovery messages

---

## Commands

### ProcessRecoveryCommand

```bash
php artisan cart:process-recovery           # Process all scheduled attempts
php artisan cart:process-recovery --limit=50
php artisan cart:process-recovery --campaign=uuid
```

### ScheduleRecoveryCommand

```bash
php artisan cart:schedule-recovery          # Schedule for all active campaigns
php artisan cart:schedule-recovery --campaign=uuid
```

---

## Events

```php
// Recovery lifecycle events
RecoveryAttemptScheduled::class
RecoveryAttemptSent::class
RecoveryAttemptDelivered::class
RecoveryAttemptOpened::class
RecoveryAttemptClicked::class
CartRecovered::class

// Campaign events
RecoveryCampaignStarted::class
RecoveryCampaignPaused::class
RecoveryCampaignCompleted::class
```

---

## Configuration

```php
// config/filament-cart.php
'recovery' => [
    'enabled' => true,
    'default_max_attempts' => 3,
    'default_delay_hours' => 24,
    'email' => [
        'from_address' => env('RECOVERY_FROM_EMAIL'),
        'from_name' => env('RECOVERY_FROM_NAME', 'Store'),
    ],
    'sms' => [
        'enabled' => false,
        'provider' => 'twilio',
    ],
    'tracking' => [
        'pixel_enabled' => true,
        'click_tracking' => true,
    ],
],
```

---

## Files to Create

| File | Type | Description |
|------|------|-------------|
| `database/migrations/..._create_cart_recovery_campaigns_table.php` | Migration | Campaigns table |
| `database/migrations/..._create_cart_recovery_attempts_table.php` | Migration | Attempts table |
| `database/migrations/..._create_cart_recovery_templates_table.php` | Migration | Templates table |
| `src/Models/RecoveryCampaign.php` | Model | Campaign model |
| `src/Models/RecoveryAttempt.php` | Model | Attempt model |
| `src/Models/RecoveryTemplate.php` | Model | Template model |
| `src/Services/RecoveryScheduler.php` | Service | Scheduling logic |
| `src/Services/RecoveryDispatcher.php` | Service | Message dispatch |
| `src/Services/RecoveryAnalytics.php` | Service | Campaign analytics |
| `src/Data/CampaignMetrics.php` | DTO | Campaign metrics |
| `src/Data/RecoveryInsight.php` | DTO | Recovery insights |
| `src/Resources/RecoveryCampaignResource.php` | Resource | Campaign management |
| `src/Resources/RecoveryTemplateResource.php` | Resource | Template management |
| `src/Pages/RecoverySettingsPage.php` | Page | Settings page |
| `src/Widgets/CampaignPerformanceWidget.php` | Widget | Campaign stats |
| `src/Widgets/RecoveryFunnelWidget.php` | Widget | Funnel visualization |
| `src/Widgets/StrategyComparisonWidget.php` | Widget | Strategy comparison |
| `src/Commands/ProcessRecoveryCommand.php` | Command | Process attempts |
| `src/Commands/ScheduleRecoveryCommand.php` | Command | Schedule attempts |

---

## Tests

- `RecoveryCampaignTest` - Campaign lifecycle tests
- `RecoverySchedulerTest` - Scheduling logic tests
- `RecoveryDispatcherTest` - Message dispatch tests
- `RecoveryAnalyticsTest` - Analytics calculation tests
