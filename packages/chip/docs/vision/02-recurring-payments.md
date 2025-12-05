# Recurring Payments (App-Layer)

> **Document:** 02 of 05  
> **Package:** `aiarmada/chip`  
> **Status:** Vision (API-Constrained)

---

## Overview

Build **app-layer recurring payment automation** using Chip's existing recurring token and charge APIs. This is NOT a Chip subscription feature - it's local scheduling that uses Chip for payment processing.

---

## API Foundation

Chip provides these endpoints that enable recurring payments:

```php
// Create purchase with recurring token
Chip::purchase()
    ->forceRecurring(true)
    ->create();

// Charge using saved token
Chip::chargePurchase($purchaseId, $recurringToken);

// Delete token
Chip::deleteRecurringToken($purchaseId);

// List client tokens
Chip::listClientRecurringTokens($clientId);
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  APP-LAYER SCHEDULING                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌───────────────┐     ┌───────────────┐                    │
│  │ Recurring     │────►│ Laravel       │                    │
│  │ Schedule      │     │ Scheduler     │                    │
│  └───────────────┘     └───────────────┘                    │
│         │                     │                              │
│         ▼                     ▼                              │
│  ┌───────────────┐     ┌───────────────┐                    │
│  │ Schedule      │────►│ Chip API      │                    │
│  │ Processor     │     │ chargePurchase│                    │
│  └───────────────┘     └───────────────┘                    │
│                                                              │
│  Local: Schedule, retry, notification                        │
│  Chip: Token storage, payment processing                     │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Local Models

### ChipRecurringSchedule

```php
/**
 * Local recurring payment schedule
 * 
 * @property string $id
 * @property string $chip_client_id
 * @property string $recurring_token_id
 * @property string $subscriber_type
 * @property string $subscriber_id
 * @property string $status
 * @property int $amount_minor
 * @property string $currency
 * @property string $interval (daily, weekly, monthly, yearly)
 * @property int $interval_count
 * @property Carbon|null $next_charge_at
 * @property Carbon|null $last_charged_at
 * @property int $failure_count
 * @property int $max_failures
 * @property Carbon|null $cancelled_at
 * @property array|null $metadata
 */
class ChipRecurringSchedule extends Model
{
    use HasUuids;
    
    protected $casts = [
        'status' => RecurringStatus::class,
        'interval' => RecurringInterval::class,
        'next_charge_at' => 'datetime',
        'last_charged_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function charges(): HasMany
    {
        return $this->hasMany(ChipRecurringCharge::class, 'schedule_id');
    }
    
    public function isActive(): bool
    {
        return $this->status === RecurringStatus::Active;
    }
    
    public function isDue(): bool
    {
        return $this->isActive() && $this->next_charge_at?->isPast();
    }
    
    public function calculateNextChargeDate(): Carbon
    {
        $base = $this->last_charged_at ?? now();
        
        return match ($this->interval) {
            RecurringInterval::Daily => $base->addDays($this->interval_count),
            RecurringInterval::Weekly => $base->addWeeks($this->interval_count),
            RecurringInterval::Monthly => $base->addMonths($this->interval_count),
            RecurringInterval::Yearly => $base->addYears($this->interval_count),
        };
    }
}

enum RecurringStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}

enum RecurringInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
```

### ChipRecurringCharge

```php
/**
 * Record of each charge attempt
 * 
 * @property string $id
 * @property string $schedule_id
 * @property string|null $chip_purchase_id
 * @property int $amount_minor
 * @property string $status
 * @property string|null $failure_reason
 * @property Carbon $attempted_at
 */
class ChipRecurringCharge extends Model
{
    use HasUuids;
    
    protected $casts = [
        'status' => ChargeStatus::class,
        'attempted_at' => 'datetime',
    ];
    
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ChipRecurringSchedule::class, 'schedule_id');
    }
}

enum ChargeStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
```

---

## Recurring Service

### ChipRecurringService

```php
class ChipRecurringService
{
    public function __construct(
        private ChipCollectClient $chip,
    ) {}
    
    /**
     * Create a recurring schedule after initial purchase
     */
    public function createSchedule(
        ChipPurchase $initialPurchase,
        Model $subscriber,
        RecurringInterval $interval,
        int $intervalCount = 1
    ): ChipRecurringSchedule {
        if (!$initialPurchase->recurring_token) {
            throw new NoRecurringTokenException();
        }
        
        return ChipRecurringSchedule::create([
            'chip_client_id' => $initialPurchase->client_id,
            'recurring_token_id' => $initialPurchase->recurring_token,
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->getKey(),
            'status' => RecurringStatus::Active,
            'amount_minor' => $initialPurchase->total_minor,
            'currency' => $initialPurchase->currency,
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'next_charge_at' => $this->calculateFirstCharge($interval, $intervalCount),
            'max_failures' => config('chip.recurring.max_failures', 3),
        ]);
    }
    
    /**
     * Process a scheduled charge
     */
    public function processCharge(ChipRecurringSchedule $schedule): ChipRecurringCharge
    {
        $charge = ChipRecurringCharge::create([
            'schedule_id' => $schedule->id,
            'amount_minor' => $schedule->amount_minor,
            'status' => ChargeStatus::Pending,
            'attempted_at' => now(),
        ]);
        
        try {
            // Create and charge purchase using Chip API
            $purchase = Chip::purchase()
                ->clientId($schedule->chip_client_id)
                ->addProduct('Recurring Payment', $schedule->amount_minor)
                ->currency($schedule->currency)
                ->create();
            
            // Charge using token
            $result = Chip::chargePurchase($purchase->id, $schedule->recurring_token_id);
            
            $charge->update([
                'chip_purchase_id' => $result->id,
                'status' => ChargeStatus::Success,
            ]);
            
            $schedule->update([
                'last_charged_at' => now(),
                'next_charge_at' => $schedule->calculateNextChargeDate(),
                'failure_count' => 0,
            ]);
            
            event(new RecurringChargeSucceeded($schedule, $charge));
            
        } catch (ChipException $e) {
            $charge->update([
                'status' => ChargeStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);
            
            $this->handleFailure($schedule, $e);
        }
        
        return $charge;
    }
    
    /**
     * Handle charge failure with retry logic
     */
    private function handleFailure(ChipRecurringSchedule $schedule, ChipException $e): void
    {
        $schedule->increment('failure_count');
        
        if ($schedule->failure_count >= $schedule->max_failures) {
            $schedule->update(['status' => RecurringStatus::Failed]);
            event(new RecurringScheduleFailed($schedule));
        } else {
            // Schedule retry with backoff
            $retryDelay = pow(2, $schedule->failure_count) * 24; // hours
            $schedule->update([
                'next_charge_at' => now()->addHours($retryDelay),
            ]);
            event(new RecurringChargeRetryScheduled($schedule, $retryDelay));
        }
    }
    
    /**
     * Cancel a recurring schedule
     */
    public function cancel(ChipRecurringSchedule $schedule): ChipRecurringSchedule
    {
        $schedule->update([
            'status' => RecurringStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        
        event(new RecurringScheduleCancelled($schedule));
        
        return $schedule;
    }
    
    /**
     * Pause a recurring schedule
     */
    public function pause(ChipRecurringSchedule $schedule): ChipRecurringSchedule
    {
        $schedule->update(['status' => RecurringStatus::Paused]);
        
        return $schedule;
    }
    
    /**
     * Resume a paused schedule
     */
    public function resume(ChipRecurringSchedule $schedule): ChipRecurringSchedule
    {
        $schedule->update([
            'status' => RecurringStatus::Active,
            'next_charge_at' => now(),
        ]);
        
        return $schedule;
    }
}
```

---

## Scheduled Job

### ProcessRecurringCharges Command

```php
class ProcessRecurringCharges extends Command
{
    protected $signature = 'chip:process-recurring';
    protected $description = 'Process due recurring payment schedules';
    
    public function handle(ChipRecurringService $service): int
    {
        $due = ChipRecurringSchedule::query()
            ->where('status', RecurringStatus::Active)
            ->where('next_charge_at', '<=', now())
            ->get();
        
        $this->info("Processing {$due->count()} due schedules");
        
        foreach ($due as $schedule) {
            try {
                $service->processCharge($schedule);
                $this->line("✓ Processed schedule {$schedule->id}");
            } catch (Throwable $e) {
                $this->error("✗ Failed schedule {$schedule->id}: {$e->getMessage()}");
                report($e);
            }
        }
        
        return self::SUCCESS;
    }
}
```

### Scheduler Registration

```php
// In kernel or bootstrap
$schedule->command('chip:process-recurring')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
```

---

## Database Schema

```php
// chip_recurring_schedules table
Schema::create('chip_recurring_schedules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('chip_client_id');
    $table->string('recurring_token_id');
    $table->uuidMorphs('subscriber');
    $table->string('status');
    $table->bigInteger('amount_minor');
    $table->string('currency', 3)->default('MYR');
    $table->string('interval');
    $table->integer('interval_count')->default(1);
    $table->timestamp('next_charge_at')->nullable();
    $table->timestamp('last_charged_at')->nullable();
    $table->integer('failure_count')->default(0);
    $table->integer('max_failures')->default(3);
    $table->timestamp('cancelled_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['status', 'next_charge_at']);
    $table->index(['subscriber_type', 'subscriber_id']);
});

// chip_recurring_charges table
Schema::create('chip_recurring_charges', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('schedule_id');
    $table->string('chip_purchase_id')->nullable();
    $table->bigInteger('amount_minor');
    $table->string('status');
    $table->text('failure_reason')->nullable();
    $table->timestamp('attempted_at');
    $table->timestamps();
    
    $table->index('schedule_id');
    $table->index('chip_purchase_id');
});
```

---

## Usage Example

```php
// After initial purchase with recurring token
$purchase = Chip::purchase()
    ->customer($user->email)
    ->addProduct('Monthly Subscription', 9900)
    ->forceRecurring(true)
    ->create();

// Create recurring schedule
$schedule = app(ChipRecurringService::class)->createSchedule(
    initialPurchase: $purchase,
    subscriber: $user,
    interval: RecurringInterval::Monthly,
);

// Cancel when needed
app(ChipRecurringService::class)->cancel($schedule);
```

---

## Important Notes

1. **This is NOT a Chip feature** - All scheduling is app-layer
2. **Token management** - Tokens expire; handle appropriately
3. **Failure handling** - Implement proper notification to customers
4. **Idempotency** - Prevent duplicate charges with locking

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-enhanced-webhooks.md](03-enhanced-webhooks.md)
