---
title: Testing
---

# Testing

Cashier CHIP provides utilities for testing billing functionality.

## Faking CHIP

Use `CashierChip::fake()` to mock all CHIP API calls:

```php
use AIArmada\CashierChip\CashierChip;
use Tests\TestCase;

class BillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        CashierChip::fake();
    }
    
    public function test_user_can_checkout(): void
    {
        $user = User::factory()->create();
        
        $checkout = $user->checkout(10000, [
            'reference' => 'Test Payment',
        ]);
        
        $this->assertNotNull($checkout->url());
    }
}
```

## Factories

The package includes factories for testing subscriptions:

### Subscription Factory

```php
use AIArmada\CashierChip\Subscription;

// Basic subscription
$subscription = Subscription::factory()->create();

// With specific user
$subscription = Subscription::factory()
    ->for($user)
    ->create();

// Active subscription
$subscription = Subscription::factory()
    ->active()
    ->create();

// With trial
$subscription = Subscription::factory()
    ->trialing()
    ->create();

// Canceled
$subscription = Subscription::factory()
    ->canceled()
    ->create();

// On grace period
$subscription = Subscription::factory()
    ->onGracePeriod()
    ->create();

// Past due
$subscription = Subscription::factory()
    ->pastDue()
    ->create();

// With specific price
$subscription = Subscription::factory()
    ->withPrice('price_monthly')
    ->create();

// Monthly/Yearly
$subscription = Subscription::factory()
    ->monthly()
    ->create();

$subscription = Subscription::factory()
    ->yearly()
    ->create();
```

### Subscription Item Factory

```php
use AIArmada\CashierChip\SubscriptionItem;

// Basic item
$item = SubscriptionItem::factory()->create();

// For specific subscription
$item = SubscriptionItem::factory()
    ->forSubscription($subscription)
    ->create();

// With quantity
$item = SubscriptionItem::factory()
    ->quantity(5)
    ->create();

// With specific price
$item = SubscriptionItem::factory()
    ->withPrice('price_addon')
    ->withProduct('prod_addon')
    ->create();
```

## Testing Subscriptions

### Creating Test Subscriptions

```php
public function test_user_can_subscribe(): void
{
    CashierChip::fake();
    
    $user = User::factory()->create();
    
    $subscription = $user->newSubscription('default', 'price_monthly')
        ->create('tok_test_token');
    
    $this->assertTrue($user->subscribed('default'));
    $this->assertTrue($subscription->active());
}
```

### Testing Subscription States

```php
public function test_subscription_can_be_canceled(): void
{
    $subscription = Subscription::factory()
        ->active()
        ->for($user)
        ->create();
    
    $subscription->cancel();
    
    $this->assertTrue($subscription->canceled());
    $this->assertTrue($subscription->onGracePeriod());
}

public function test_subscription_trial(): void
{
    $subscription = Subscription::factory()
        ->trialing(now()->addDays(14))
        ->for($user)
        ->create();
    
    $this->assertTrue($subscription->onTrial());
    $this->assertTrue($subscription->valid());
}
```

## Testing Webhooks

### Simulating Webhooks

```php
public function test_webhook_updates_payment_status(): void
{
    $user = User::factory()->create();
    
    // Create a pending purchase
    // ...
    
    // Simulate webhook
    $response = $this->postJson('/chip/webhook', [
        'event_type' => 'purchase.payment_successful',
        'id' => $purchaseId,
        'client_id' => $user->chipId(),
        'status' => 'paid',
    ]);
    
    $response->assertOk();
}
```

### Testing Webhook Events

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Event;

public function test_payment_event_is_dispatched(): void
{
    Event::fake([PaymentSucceeded::class]);
    
    $this->postJson('/chip/webhook', [
        'event_type' => 'purchase.payment_successful',
        'id' => 'purchase-123',
        'status' => 'paid',
    ]);
    
    Event::assertDispatched(PaymentSucceeded::class);
}
```

## Testing Charges

```php
public function test_user_can_be_charged(): void
{
    CashierChip::fake();
    
    $user = User::factory()->create();
    $user->updateDefaultPaymentMethod('tok_test');
    
    $payment = $user->charge(10000);
    
    $this->assertNotNull($payment->id());
}
```

## Testing Checkout

```php
public function test_checkout_redirects(): void
{
    CashierChip::fake();
    
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->post('/checkout', [
            'amount' => 10000,
        ]);
    
    $response->assertRedirect();
}
```

## Test Helpers

### Assert Subscribed

```php
$this->assertTrue($user->subscribed('default'));
$this->assertTrue($user->subscribedToPrice('price_monthly'));
```

### Assert Not Subscribed

```php
$this->assertFalse($user->subscribed('premium'));
```

### Assert Has Payment Method

```php
$this->assertTrue($user->hasDefaultPaymentMethod());
```

## Mocking the Gateway

For more control, mock the underlying CHIP gateway:

```php
use AIArmada\Chip\ChipCollect;
use Mockery;

public function test_with_mocked_gateway(): void
{
    $mockChip = Mockery::mock(ChipCollect::class);
    $mockChip->shouldReceive('createPurchase')
        ->once()
        ->andReturn([
            'id' => 'purchase-123',
            'checkout_url' => 'https://chip.test/checkout',
        ]);
    
    $this->app->instance(ChipCollect::class, $mockChip);
    
    // Your test...
}
```

## Configuration for Testing

```php
// phpunit.xml
<env name="CHIP_BRAND_ID" value="test-brand"/>
<env name="CHIP_SECRET_KEY" value="test-secret"/>
<env name="CHIP_VERIFY_WEBHOOK" value="false"/>
```

## Database Setup

Use the RefreshDatabase trait:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillingTest extends TestCase
{
    use RefreshDatabase;
    
    // Tests...
}
```

Ensure migrations are published:

```bash
php artisan vendor:publish --tag=cashier-chip-migrations
```
