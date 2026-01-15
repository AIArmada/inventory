---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### API Connection Failed

**Symptom:** `ChipApiException: API request failed with status 401`

**Causes:**
1. Invalid API key
2. Wrong environment (sandbox vs production)

**Solutions:**
```bash
# Verify your credentials
php artisan chip:health

# Check environment
echo $CHIP_ENVIRONMENT  # Should be 'sandbox' or 'production'
```

### Webhook Signature Verification Failed

**Symptom:** `WebhookVerificationException: Invalid signature`

**Causes:**
1. Wrong public key configured
2. Request body modified by middleware

**Solutions:**
```php
// Ensure raw body is preserved in webhook route
// Add to RouteServiceProvider or webhook route group:
Route::middleware(['api'])->withoutMiddleware([
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
])->group(function () {
    Route::post('/chip/webhook', [WebhookController::class, 'handle']);
});
```

### Purchases Not Showing (Multi-Tenancy)

**Symptom:** Purchases exist in database but don't appear in queries

**Cause:** Owner scoping is enabled but owner not resolved

**Solutions:**
```php
// Option 1: Ensure owner is set in context
OwnerContext::withOwner($tenant, function () {
    $purchases = Purchase::all();  // Now owner-scoped
});

// Option 2: Explicitly bypass for admin views
$allPurchases = Purchase::query()
    ->withoutOwnerScope()
    ->get();
```

### Webhook Not Routing to Owner

**Symptom:** Webhooks received but no owner assigned

**Solution:** Configure brand ID mapping in `config/chip.php`:
```php
'owner' => [
    'enabled' => true,
    'webhook_brand_id_map' => [
        'your-brand-uuid' => [
            'type' => \App\Models\Tenant::class,
            'id' => 1,
        ],
    ],
],
```

## PostgreSQL UUID Issues

**Symptom:** `invalid input syntax for type uuid: ""`

**Cause:** CHIP API sometimes returns empty strings for nullable UUID fields

**Solution:** This is handled automatically by `PurchaseData::sanitizeUuidFields()`. If you're manually inserting data, ensure empty strings are converted to `null`.

## Debugging

### Enable Request/Response Logging

```env
CHIP_LOGGING_ENABLED=true
CHIP_LOG_REQUESTS=true
CHIP_LOG_RESPONSES=true
```

### View Webhook Payloads

```env
CHIP_WEBHOOK_LOG_PAYLOADS=true
```

Check logs in `storage/logs/laravel.log` or your configured channel.

### Test Webhooks Locally

Use the testing utilities:

```php
use AIArmada\Chip\Testing\WebhookSimulator;

$simulator = new WebhookSimulator();

// Simulate a paid purchase webhook
$simulator->simulatePurchasePaid($purchaseId);
```

## Health Check

Run the comprehensive health check:

```bash
php artisan chip:health
```

This verifies:
- API key and brand ID are configured
- API connection is working
- Public key for webhook verification (if enabled)
- Database tables exist

## Getting Help

1. Check the [CHIP API Documentation](https://docs.chip-in.asia/)
2. Review the [API Reference](api-reference.md)
3. Enable debug logging to capture request/response details
