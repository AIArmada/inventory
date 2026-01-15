# API Reference

Complete method reference for CHIP package.

## Base URLs

| Service | Environment | URL |
|---------|-------------|-----|
| Collect | All | `https://gate.chip-in.asia/api/v1/` |
| Send | Sandbox | `https://staging-api.chip-in.asia/api/` |
| Send | Production | `https://api.chip-in.asia/api/` |

## Authentication

### Collect

```http
Authorization: Bearer {CHIP_COLLECT_API_KEY}
```

### Send

```http
Authorization: Bearer {CHIP_SEND_API_KEY}
epoch: {unix_timestamp}
checksum: {hmac_sha256(epoch, CHIP_SEND_API_SECRET)}
```

## ChipGateway

```php
use AIArmada\Chip\Gateways\ChipGateway;

$gateway = app(ChipGateway::class);

$gateway->getName(): string                    // 'chip'
$gateway->getDisplayName(): string             // 'CHIP'
$gateway->isTestMode(): bool

$gateway->createPayment(
    CheckoutableInterface $checkoutable,
    ?CustomerInterface $customer,
    array $options
): PaymentIntentInterface

$gateway->getPayment(string $paymentId): PaymentIntentInterface
$gateway->cancelPayment(string $paymentId): PaymentIntentInterface
$gateway->refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
$gateway->capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface
$gateway->getPaymentMethods(array $filters = []): array
$gateway->supports(string $feature): bool
$gateway->getWebhookHandler(): WebhookHandlerInterface
```

## ChipCollectService (Chip Facade)

```php
use AIArmada\Chip\Facades\Chip;

// Purchases
Chip::purchase(): PurchaseBuilder
Chip::createPurchase(array $data): Purchase
Chip::getPurchase(string $id): Purchase
Chip::cancelPurchase(string $id): Purchase
Chip::refundPurchase(string $id, ?int $amount = null): Purchase
Chip::capturePurchase(string $id, ?int $amount = null): Purchase
Chip::releasePurchase(string $id): Purchase
Chip::markPurchaseAsPaid(string $id, ?int $paidOn = null): Purchase
Chip::resendInvoice(string $id): Purchase
Chip::getPaymentMethods(array $filters = []): array

// Clients
Chip::createClient(array $data): Client
Chip::getClient(string $id): Client
Chip::listClients(array $filters = []): array
Chip::updateClient(string $id, array $data): Client
Chip::partialUpdateClient(string $id, array $data): Client
Chip::deleteClient(string $id): void

// Account
Chip::getAccountBalance(): array
Chip::getAccountTurnover(array $filters = []): array
Chip::listCompanyStatements(array $filters = []): array
Chip::getCompanyStatement(string $id): CompanyStatement
Chip::cancelCompanyStatement(string $id): CompanyStatement

// Webhooks
Chip::createWebhook(array $data): array
Chip::getWebhook(string $id): array
Chip::updateWebhook(string $id, array $data): array
Chip::deleteWebhook(string $id): void
Chip::listWebhooks(array $filters = []): array

// Public Key
Chip::getPublicKey(): string
Chip::getBrandId(): string
```

## ChipSendService (ChipSend Facade)

```php
use AIArmada\Chip\Facades\ChipSend;

// Send Instructions
ChipSend::createSendInstruction(
    int $amountInCents,
    string $currency,
    string $recipientBankAccountId,
    string $description,
    string $reference,
    string $email
): SendInstruction

ChipSend::getSendInstruction(string $id): SendInstruction
ChipSend::listSendInstructions(array $filters = []): array
ChipSend::cancelSendInstruction(string $id): SendInstruction
ChipSend::deleteSendInstruction(string $id): void
ChipSend::resendSendInstructionWebhook(string $id): array

// Bank Accounts
ChipSend::createBankAccount(
    string $bankCode,
    string $accountNumber,
    string $accountHolderName,
    ?string $reference = null
): BankAccount

ChipSend::getBankAccount(string $id): BankAccount
ChipSend::listBankAccounts(array $filters = []): array
ChipSend::updateBankAccount(string $id, array $data): BankAccount
ChipSend::deleteBankAccount(string $id): void
ChipSend::resendBankAccountWebhook(string $id): array

// Send Limits
ChipSend::getSendLimit(int|string $id): SendLimit

// Groups
ChipSend::createGroup(array $data): array
ChipSend::getGroup(string $id): array
ChipSend::listGroups(array $filters = []): array
ChipSend::updateGroup(string $id, array $data): array
ChipSend::deleteGroup(string $id): void

// Accounts
ChipSend::listAccounts(): array

// Webhooks
ChipSend::createSendWebhook(array $data): SendWebhook
ChipSend::getSendWebhook(string $id): SendWebhook
ChipSend::listSendWebhooks(array $filters = []): array
ChipSend::updateSendWebhook(string $id, array $data): SendWebhook
ChipSend::deleteSendWebhook(string $id): void
```

## PurchaseBuilder

```php
Chip::purchase()
    ->brand(string $brandId): self
    ->currency(string $currency = 'MYR'): self
    ->customer(string $email, ?string $fullName, ?string $phone, ?string $country): self
    ->email(string $email): self
    ->clientId(string $clientId): self
    ->billingAddress(string $street, string $city, string $zip, ?string $state, ?string $country): self
    ->shippingAddress(string $street, string $city, string $zip, ?string $state, ?string $country): self
    ->addProductCents(string $name, int $price, int $quantity = 1, int $discount = 0, float $taxPercent = 0): self
    ->addProductMoney(string $name, Money $price, int $quantity = 1, ?Money $discount = null, float $taxPercent = 0): self
    ->addProductObject(Product $product): self
    ->addLineItem(LineItemInterface $item): self
    ->fromCheckoutable(CheckoutableInterface $checkoutable): self
    ->fromCustomer(CustomerInterface $customer): self
    ->reference(string $reference): self
    ->successUrl(string $url): self
    ->failureUrl(string $url): self
    ->cancelUrl(string $url): self
    ->redirects(string $success, ?string $failure, ?string $cancel): self
    ->webhook(string $url): self
    ->sendReceipt(bool $send = true): self
    ->preAuthorize(bool $skipCapture = true): self
    ->forceRecurring(bool $force = true): self
    ->due(int $timestamp, bool $strict = false): self
    ->notes(string $notes): self
    ->metadata(array $metadata): self
    ->toArray(): array
    ->create(): Purchase
    ->save(): Purchase
```

## Data Objects

### Purchase

```php
$purchase->id: string
$purchase->status: string
$purchase->checkout_url: ?string
$purchase->reference: ?string
$purchase->client: ClientDetails
$purchase->purchase: PurchaseDetails
$purchase->payment: ?Payment

$purchase->getAmount(): Money
$purchase->getAmountInCents(): int
$purchase->getCurrency(): string
$purchase->getCheckoutUrl(): ?string
$purchase->getClientId(): ?string
$purchase->getMetadata(): ?array
$purchase->isRecurring(): bool
$purchase->isPaid(): bool
$purchase->isRefunded(): bool
$purchase->isCancelled(): bool
$purchase->isOnHold(): bool
$purchase->isPending(): bool
$purchase->hasError(): bool
$purchase->canBeRefunded(): bool
$purchase->getRefundableAmount(): Money
$purchase->getCreatedAt(): Carbon
$purchase->getUpdatedAt(): Carbon
```

### Payment

```php
$payment->amount: Money
$payment->net_amount: Money
$payment->fee_amount: Money
$payment->pending_amount: Money
$payment->payment_type: string
$payment->is_outgoing: bool

$payment->getAmountInCents(): int
$payment->getNetAmountInCents(): int
$payment->getFeeAmountInCents(): int
$payment->getCurrency(): string
$payment->isPaid(): bool
$payment->getPaidAt(): ?Carbon
```

### SendInstruction

```php
$instruction->id: int
$instruction->bank_account_id: int
$instruction->amount: string
$instruction->state: string
$instruction->email: string
$instruction->description: string
$instruction->reference: string
$instruction->receipt_url: ?string

$instruction->getAmountInMinorUnits(): int
$instruction->isReceived(): bool
$instruction->isEnquiring(): bool
$instruction->isExecuting(): bool
$instruction->isReviewing(): bool
$instruction->isAccepted(): bool
$instruction->isCompleted(): bool
$instruction->isRejected(): bool
$instruction->isDeleted(): bool
$instruction->isPending(): bool
```

### BankAccount

```php
$account->id: string
$account->bank_code: string
$account->account_number: string
$account->name: string
$account->status: string
$account->reference: ?string
```

## Conventions

- Amounts in **cents** (sen) for API communication
- Use `Money` objects for type-safe calculations
- Timestamps as Unix epoch or ISO8601
- Omit optional fields rather than send empty strings
