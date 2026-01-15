---
title: API Reference
---

# API Reference

Complete reference for Cashier CHIP classes and methods.

## Billable Trait

Methods available on models using the `Billable` trait.

### Customer Management

```php
// Create CHIP customer
$user->createAsChipCustomer(array $options = []): Client

// Create if not exists
$user->createAsChipCustomerIfNotExists(): ?Client

// Get CHIP client ID
$user->chipId(): ?string

// Check if has CHIP ID
$user->hasChipId(): bool

// Get CHIP customer object
$user->asChipCustomer(): Client

// Update CHIP customer
$user->updateChipCustomer(array $data): Client

// Sync local data to CHIP
$user->syncToChip(): Client

// Get customer data for CHIP
$user->chipCustomerData(): array
```

### Payment Methods

```php
// Get all payment methods
$user->paymentMethods(): Collection

// Get default payment method
$user->defaultPaymentMethod(): ?string

// Check if has default payment method
$user->hasDefaultPaymentMethod(): bool

// Update default payment method
$user->updateDefaultPaymentMethod(string $token): void

// Add payment method
$user->addPaymentMethod(string $token, array $details = []): void

// Delete payment method
$user->deletePaymentMethod(string $token): void

// Create setup purchase (for adding payment methods)
$user->createSetupPurchase(array $options = []): Purchase

// Get setup purchase URL
$user->setupPaymentMethodUrl(array $options = []): string
```

### Charges

```php
// Charge with default payment method
$user->charge(int $amount, array $options = []): Payment

// Charge with specific recurring token
$user->chargeWithRecurringToken(
    int $amount, 
    string $recurringToken, 
    array $options = []
): Payment
```

### Checkout

```php
// Create checkout session
$user->checkout(int $amount, array $options = []): Checkout
```

### Subscriptions

```php
// Create new subscription builder
$user->newSubscription(string $type, string $price): SubscriptionBuilder

// Get subscription by type
$user->subscription(string $type = 'default'): ?Subscription

// Get all subscriptions
$user->subscriptions(): HasMany

// Check if subscribed
$user->subscribed(string $type = 'default'): bool

// Check if subscribed to specific price
$user->subscribedToPrice(string $price, string $type = 'default'): bool

// Check if on trial for any subscription
$user->onTrial(string $type = 'default'): bool

// Check if on grace period
$user->onGracePeriod(string $type = 'default'): bool
```

---

## Checkout Class

### Static Methods

```php
// Guest checkout builder
Checkout::guest(): CheckoutBuilder

// Customer checkout builder
Checkout::customer(Model $owner): CheckoutBuilder

// Create checkout directly
Checkout::create(
    ?Model $owner, 
    int $amount, 
    array $options = []
): Checkout
```

### Instance Methods

```php
// Get checkout URL
$checkout->url(): ?string

// Get purchase ID
$checkout->id(): string

// Redirect to checkout
$checkout->redirect(): RedirectResponse

// Get owner model
$checkout->owner(): ?Model

// Get CHIP Purchase object
$checkout->asChipPurchase(): Purchase

// Convert to Payment object
$checkout->asPayment(): Payment

// Serialize to array
$checkout->toArray(): array

// Serialize to JSON
$checkout->toJson(): string
```

---

## CheckoutBuilder Class

```php
// Set recurring token request
$builder->recurring(bool $recurring = true): self

// Set success URL
$builder->successUrl(string $url): self

// Set cancel URL
$builder->cancelUrl(string $url): self

// Set webhook URL
$builder->webhookUrl(string $url): self

// Add metadata
$builder->withMetadata(array $metadata): self

// Add product
$builder->addProduct(string $name, int $price, int $quantity = 1): self

// Set products
$builder->products(array $products): self

// Set currency
$builder->currency(string $currency): self

// Create checkout
$builder->create(int $amount, array $options = []): Checkout

// Create charge checkout
$builder->charge(int $amount, string $description = 'Payment', array $options = []): Checkout
```

---

## Payment Class

```php
// Get payment ID
$payment->id(): string

// Get status
$payment->status(): string

// Get amount in cents
$payment->rawAmount(): int

// Get checkout URL
$payment->checkoutUrl(): ?string

// Get recurring token
$payment->recurringToken(): ?string

// Check status
$payment->isSuccessful(): bool
$payment->isPending(): bool
$payment->isFailed(): bool

// Get CHIP Purchase object
$payment->asChipPurchase(): Purchase

// Serialize
$payment->toArray(): array
$payment->toJson(): string
```

---

## Subscription Class

### Status Checks

```php
$subscription->valid(): bool
$subscription->active(): bool
$subscription->incomplete(): bool
$subscription->pastDue(): bool
$subscription->canceled(): bool
$subscription->ended(): bool
$subscription->onTrial(): bool
$subscription->hasExpiredTrial(): bool
$subscription->onGracePeriod(): bool
$subscription->recurring(): bool
$subscription->hasIncompletePayment(): bool
```

### Price/Product Checks

```php
$subscription->hasMultiplePrices(): bool
$subscription->hasSinglePrice(): bool
$subscription->hasProduct(string $product): bool
$subscription->hasPrice(string $price): bool
$subscription->findItemOrFail(string $price): SubscriptionItem
```

### Management

```php
// Cancel
$subscription->cancel(): self
$subscription->cancelAt(DateTimeInterface|int $endsAt): self
$subscription->cancelNow(): self

// Resume
$subscription->resume(): self

// Swap price
$subscription->swap(string|array $prices, array $options = []): self

// Quantity
$subscription->incrementQuantity(int $count = 1, ?string $price = null): self
$subscription->decrementQuantity(int $count = 1, ?string $price = null): self
$subscription->updateQuantity(int $quantity, ?string $price = null): self
```

### Trial Management

```php
$subscription->skipTrial(): self
$subscription->endTrial(): self
$subscription->extendTrial(CarbonInterface $date): self
```

### Billing

```php
$subscription->charge(?int $amount = null): Payment
$subscription->recurringToken(): ?string
$subscription->setRecurringToken(string $token): self
$subscription->currentPeriodStart(): ?CarbonInterface
$subscription->currentPeriodEnd(): ?CarbonInterface
```

### Relationships

```php
$subscription->user(): BelongsTo
$subscription->owner(): BelongsTo
$subscription->items(): HasMany
```

### Scopes

```php
Subscription::active()
Subscription::canceled()
Subscription::notCanceled()
Subscription::ended()
Subscription::onTrial()
Subscription::expiredTrial()
Subscription::notOnTrial()
Subscription::onGracePeriod()
Subscription::notOnGracePeriod()
Subscription::incomplete()
Subscription::pastDue()
Subscription::recurring()
```

---

## SubscriptionBuilder Class

```php
// Set trial days
$builder->trialDays(int $days): self

// Set trial until date
$builder->trialUntil(CarbonInterface $date): self

// Skip trial
$builder->skipTrial(): self

// Set billing interval
$builder->daily(): self
$builder->weekly(): self
$builder->monthly(): self
$builder->yearly(): self
$builder->billingInterval(string $interval, int $count = 1): self

// Set quantity
$builder->quantity(int $quantity): self

// Set metadata
$builder->withMetadata(array $metadata): self

// Create subscription
$builder->create(?string $recurringToken = null): Subscription

// Create via checkout
$builder->checkout(array $options = []): Checkout
```

---

## SubscriptionItem Class

```php
// Get subscription
$item->subscription(): BelongsTo

// Quantity management
$item->incrementQuantity(int $count = 1): self
$item->decrementQuantity(int $count = 1): self
$item->updateQuantity(int $quantity): self

// Swap price
$item->swap(string $price, array $options = []): self

// Status checks
$item->onTrial(): bool
$item->onGracePeriod(): bool

// Get total amount
$item->totalAmount(): int
```

---

## CashierChip Class

### Static Configuration

```php
// Set customer model
CashierChip::useCustomerModel(string $model): void

// Set subscription model
CashierChip::useSubscriptionModel(string $model): void

// Set subscription item model
CashierChip::useSubscriptionItemModel(string $model): void

// Enable fake mode for testing
CashierChip::fake(): void

// Format amount for display
CashierChip::formatAmount(int $amount, string $currency = null): string

// Find billable by CHIP client ID
CashierChip::findBillable(string $chipId): ?Model
```

### Instance Methods

```php
// Get CHIP instance
$cashier = CashierChip::chip();

// Access purchase builder
$cashier->purchase(): PurchaseBuilder

// Access client API
$cashier->client(): ClientApi
```

---

## Constants

### Subscription Status

```php
Subscription::STATUS_ACTIVE = 'active'
Subscription::STATUS_CANCELED = 'canceled'
Subscription::STATUS_INCOMPLETE = 'incomplete'
Subscription::STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired'
Subscription::STATUS_PAST_DUE = 'past_due'
Subscription::STATUS_TRIALING = 'trialing'
Subscription::STATUS_UNPAID = 'unpaid'
Subscription::STATUS_PAUSED = 'paused'
```
