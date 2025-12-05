# Enhanced Webhooks

> **Document:** 03 of 05  
> **Package:** `aiarmada/chip`  
> **Status:** Vision (API-Constrained)

---

## Overview

Evolve the existing webhook system into a **robust, scalable event processing pipeline** with enrichment, intelligent routing, retry strategies, and real-time monitoring.

---

## Chip Webhook Events (API-Supported)

### Collect Events
| Event | Description |
|-------|-------------|
| `purchase.created` | Purchase created |
| `purchase.paid` | Payment completed |
| `purchase.cancelled` | Purchase cancelled |
| `purchase.refunded` | Refund processed |
| `payment.created` | Payment attempt started |
| `payment.paid` | Payment confirmed |
| `payment.failed` | Payment attempt failed |

### Send Events
| Event | Description |
|-------|-------------|
| `send_instruction.received` | Instruction received |
| `send_instruction.completed` | Transfer successful |
| `send_instruction.rejected` | Transfer failed |
| `bank_account.verified` | Account verified |
| `bank_account.rejected` | Account rejected |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  ENHANCED WEBHOOK PIPELINE                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Chip API ──► Webhook Endpoint                              │
│                    │                                         │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Validator   │ ─── Signature verification      │
│            └───────┬───────┘                                 │
│                    │                                         │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Enricher    │ ─── Add local context           │
│            └───────┬───────┘                                 │
│                    │                                         │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Router      │ ─── Route to handlers           │
│            └───────┬───────┘                                 │
│                    │                                         │
│        ┌───────────┼───────────┐                             │
│        ▼           ▼           ▼                             │
│    [Purchase]  [Payment]   [Send]                            │
│    Handlers    Handlers    Handlers                          │
│        │           │           │                             │
│        └───────────┼───────────┘                             │
│                    ▼                                         │
│            ┌───────────────┐                                 │
│            │   Publisher   │ ─── Emit Laravel events         │
│            └───────────────┘                                 │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Enhanced Webhook Controller

```php
class EnhancedWebhookController extends Controller
{
    public function __construct(
        private WebhookValidator $validator,
        private WebhookEnricher $enricher,
        private WebhookRouter $router,
        private WebhookLogger $logger,
    ) {}
    
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        
        // Validate signature
        if (!$this->validator->validate($request)) {
            $this->logger->logInvalidSignature($request);
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        $payload = $request->all();
        $eventType = $payload['event'] ?? null;
        
        if (!$eventType) {
            return response()->json(['error' => 'Missing event type'], 400);
        }
        
        // Create webhook log with idempotency check
        $idempotencyKey = $this->generateIdempotencyKey($payload);
        
        if ($this->logger->isDuplicate($idempotencyKey)) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }
        
        $log = $this->logger->createLog($eventType, $payload, $request, $idempotencyKey);
        
        try {
            // Enrich payload with local context
            $enrichedPayload = $this->enricher->enrich($eventType, $payload);
            
            // Route to appropriate handler
            $result = $this->router->route($eventType, $enrichedPayload);
            
            // Mark as processed
            $log->markProcessed(microtime(true) - $startTime);
            
            return response()->json(['success' => true]);
            
        } catch (RetryableException $e) {
            $log->markForRetry($e->getMessage());
            return response()->json(['error' => 'Retry later'], 503);
            
        } catch (Throwable $e) {
            $log->markFailed($e);
            report($e);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
    
    private function generateIdempotencyKey(array $payload): string
    {
        return hash('sha256', json_encode([
            'event' => $payload['event'] ?? null,
            'object_id' => $payload['data']['id'] ?? null,
            'created' => $payload['created'] ?? null,
        ]));
    }
}
```

---

## Webhook Enricher

```php
class WebhookEnricher
{
    public function enrich(string $event, array $payload): EnrichedWebhookPayload
    {
        $enriched = new EnrichedWebhookPayload($event, $payload);
        
        // Add local purchase context if available
        if ($purchaseId = $payload['data']['id'] ?? null) {
            $localPurchase = ChipPurchase::where('chip_id', $purchaseId)->first();
            if ($localPurchase) {
                $enriched->setLocalPurchase($localPurchase);
                $enriched->setOwner($localPurchase->owner);
            }
        }
        
        // Add timing context
        $enriched->setReceivedAt(now());
        $enriched->setEventTimestamp(
            isset($payload['created']) ? Carbon::parse($payload['created']) : now()
        );
        
        return $enriched;
    }
}
```

---

## Event Router

```php
class WebhookRouter
{
    private array $handlers = [
        'purchase.paid' => PurchasePaidHandler::class,
        'purchase.cancelled' => PurchaseCancelledHandler::class,
        'purchase.refunded' => PurchaseRefundedHandler::class,
        'payment.failed' => PaymentFailedHandler::class,
        'send_instruction.completed' => SendCompletedHandler::class,
        'send_instruction.rejected' => SendRejectedHandler::class,
        'bank_account.verified' => BankAccountVerifiedHandler::class,
    ];
    
    public function route(string $event, EnrichedWebhookPayload $payload): WebhookResult
    {
        $handlerClass = $this->handlers[$event] ?? null;
        
        if (!$handlerClass) {
            // Unknown event - log but don't fail
            return WebhookResult::skipped("No handler for event: {$event}");
        }
        
        $handler = app($handlerClass);
        
        return $handler->handle($payload);
    }
}
```

---

## Handler Example

```php
class PurchasePaidHandler implements WebhookHandler
{
    public function handle(EnrichedWebhookPayload $payload): WebhookResult
    {
        $localPurchase = $payload->localPurchase;
        
        if (!$localPurchase) {
            // Purchase not in our database - might be from dashboard
            return WebhookResult::skipped('Purchase not found locally');
        }
        
        // Update local status
        $localPurchase->update([
            'status' => PurchaseStatus::Paid,
            'paid_at' => now(),
        ]);
        
        // Sync recurring schedule if applicable
        if ($localPurchase->recurring_schedule_id) {
            $this->updateRecurringSchedule($localPurchase);
        }
        
        // Emit Laravel event
        event(new PurchasePaid($localPurchase));
        
        return WebhookResult::handled();
    }
}
```

---

## Retry Manager

```php
class WebhookRetryManager
{
    private array $backoffSchedule = [
        1 => 60,        // 1 minute
        2 => 300,       // 5 minutes
        3 => 900,       // 15 minutes
        4 => 3600,      // 1 hour
        5 => 14400,     // 4 hours
    ];
    
    public function shouldRetry(ChipWebhookLog $log): bool
    {
        if ($log->status !== 'failed') {
            return false;
        }
        
        return $log->retry_count < count($this->backoffSchedule);
    }
    
    public function retry(ChipWebhookLog $log): WebhookResult
    {
        $log->increment('retry_count');
        $log->update(['last_retry_at' => now()]);
        
        try {
            $payload = $log->payload;
            $enriched = app(WebhookEnricher::class)->enrich($log->event, $payload);
            $result = app(WebhookRouter::class)->route($log->event, $enriched);
            
            if ($result->isSuccess()) {
                $log->markProcessed(0);
            }
            
            return $result;
            
        } catch (Throwable $e) {
            $log->update(['last_error' => $e->getMessage()]);
            return WebhookResult::failed($e->getMessage());
        }
    }
}
```

---

## Webhook Monitor

```php
class WebhookMonitor
{
    public function getHealth(): WebhookHealth
    {
        $last24Hours = now()->subDay();
        
        $stats = ChipWebhookLog::query()
            ->where('created_at', '>=', $last24Hours)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                AVG(processing_time_ms) as avg_processing_time
            ')
            ->first();
        
        return new WebhookHealth(
            total: $stats->total ?? 0,
            processed: $stats->processed ?? 0,
            failed: $stats->failed ?? 0,
            successRate: $stats->total > 0 
                ? round($stats->processed / $stats->total * 100, 2) 
                : 100,
            avgProcessingTime: $stats->avg_processing_time ?? 0,
            isHealthy: ($stats->failed ?? 0) / max(1, $stats->total) < 0.05,
        );
    }
    
    public function getEventDistribution(): array
    {
        return ChipWebhookLog::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();
    }
}
```

---

## Enhanced Webhook Log Schema

```php
Schema::create('chip_webhook_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('event');
    $table->json('payload');
    $table->string('status')->default('pending');
    $table->integer('retry_count')->default(0);
    $table->timestamp('processed_at')->nullable();
    $table->timestamp('last_retry_at')->nullable();
    $table->text('last_error')->nullable();
    $table->string('idempotency_key')->unique()->nullable();
    $table->decimal('processing_time_ms', 10, 3)->nullable();
    $table->string('ip_address')->nullable();
    $table->timestamps();
    
    $table->index('event');
    $table->index('status');
    $table->index(['status', 'retry_count']);
});
```

---

## Scheduled Commands

```php
// Retry failed webhooks
$schedule->command('chip:retry-webhooks')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Clean old webhooks (keep 30 days)
$schedule->command('chip:clean-webhooks --days=30')
    ->dailyAt('03:00');
```

---

## Navigation

**Previous:** [02-recurring-payments.md](02-recurring-payments.md)  
**Next:** [04-local-analytics.md](04-local-analytics.md)
