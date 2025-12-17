# Chip Package Test Coverage Tracking

> **Current Coverage:** 69.2%  
> **Target Coverage:** 80%  
> **Last Updated:** 2025-12-17

## Priority Files (0% Coverage) - CRITICAL

These files have **ZERO** test coverage and MUST be addressed first:

| File | Lines | Priority | Status |
|------|-------|----------|--------|
| `Commands/AggregateMetricsCommand` | ~50 | HIGH | ⏳ TODO |
| `Commands/CleanWebhooksCommand` | ~40 | HIGH | ⏳ TODO |
| `Commands/ProcessRecurringCommand` | ~60 | HIGH | ⏳ TODO |
| `Commands/RetryWebhooksCommand` | ~50 | HIGH | ⏳ TODO |
| `Services/LocalAnalyticsService` | ~100 | HIGH | ⏳ TODO |
| `Webhooks/ProcessChipWebhook` | ~50 | HIGH | ⏳ TODO |
| `Webhooks/WebhookMonitor` | ~60 | HIGH | ⏳ TODO |

## Low Coverage Files (<50%) - HIGH PRIORITY

| File | Current % | Target | Status |
|------|-----------|--------|--------|
| `Gateways/ChipWebhookHandler` | 1.6% | 80% | ⏳ TODO |
| `Services/RecurringService` | 3.4% | 80% | ⏳ TODO |
| `Listeners/StoreWebhookData` | 6.8% | 80% | ⏳ TODO |
| `Listeners/GenerateDocOnPayment` | 8.9% | 80% | ⏳ TODO |
| `Services/MetricsAggregator` | 9.3% | 80% | ⏳ TODO |
| `Events/PurchaseSubscriptionChargeFailure` | 12.5% | 80% | ⏳ TODO |
| `Listeners/GenerateDocOnRefund` | 12.7% | 80% | ⏳ TODO |
| `Gateways/ChipGateway` | 18.6% | 80% | ⏳ TODO |
| `Webhooks/Handlers/PaymentFailedHandler` | 18.8% | 80% | ⏳ TODO |
| `Webhooks/Handlers/PurchaseRefundedHandler` | 21.4% | 80% | ⏳ TODO |
| `Webhooks/WebhookRetryManager` | 22.9% | 80% | ⏳ TODO |
| `Webhooks/Handlers/PurchaseCancelledHandler` | 25.0% | 80% | ⏳ TODO |
| `Webhooks/Handlers/PurchasePaidHandler` | 25.0% | 80% | ⏳ TODO |
| `Webhooks/Handlers/SendRejectedHandler` | 26.3% | 80% | ⏳ TODO |
| `Models/ChipIntegerModel` | 33.3% | 80% | ⏳ TODO |
| `Models/SendInstruction` | 33.3% | 80% | ⏳ TODO |
| `Webhooks/Handlers/SendCompletedHandler` | 33.3% | 80% | ⏳ TODO |
| `Facades/Chip` | 33.3% | 80% | ⏳ TODO |
| `Models/CompanyStatement` | 35.3% | 80% | ⏳ TODO |
| `Models/SendLimit` | 37.5% | 80% | ⏳ TODO |
| `Data/BillingTemplateClientData` | 42.5% | 80% | ⏳ TODO |
| `Models/BankAccount` | 47.1% | 80% | ⏳ TODO |
| `Testing/WebhookSimulator` | 48.3% | 80% | ⏳ TODO |

## Medium Coverage Files (50-79%) - MEDIUM PRIORITY

| File | Current % | Target | Status |
|------|-----------|--------|--------|
| `Events/PurchaseHold` | 50.0% | 80% | ⏳ TODO |
| `Events/PurchasePreauthorized` | 50.0% | 80% | ⏳ TODO |
| `Models/ChipModel` | 50.0% | 80% | ⏳ TODO |
| `Models/Webhook` | 50.0% | 80% | ⏳ TODO |
| `Webhooks/WebhookLogger` | 50.0% | 80% | ⏳ TODO |
| `Data/PayoutData` | 51.0% | 80% | ⏳ TODO |
| `Models/DailyMetric` | 64.3% | 80% | ⏳ TODO |
| `Testing/SimulatesWebhooks` | 65.0% | 80% | ⏳ TODO |
| `Data/ChipData` | 66.7% | 80% | ⏳ TODO |
| `Commands/ChipHealthCheckCommand` | 77.5% | 80% | ⏳ TODO |
| `Http/Middleware/VerifyWebhookSignature` | 79.2% | 80% | ⏳ TODO |

## Good Coverage Files (80-99%) - LOW PRIORITY

| File | Current % | Notes |
|------|-----------|-------|
| `Support/DocsIntegrationRegistrar` | 80.0% | ✅ OK |
| `Enums/PurchaseStatus` | 81.4% | ✅ OK |
| `Services/WebhookService` | 84.5% | ✅ OK |
| `Data/SendInstructionData` | 85.0% | ✅ OK |
| `Gateways/ChipPaymentIntent` | 85.0% | ✅ OK |
| `Models/Purchase` | 85.1% | ✅ OK |
| `Clients/Http/BaseHttpClient` | 86.6% | ✅ OK |
| `Services/ChipSendService` | 87.2% | ✅ OK |
| `Services/ChipCollectService` | 87.8% | ✅ OK |
| `Clients/ChipCollectClient` | 90.0% | ✅ OK |
| `Clients/ChipSendClient` | 90.0% | ✅ OK |
| `Services/Collect/PurchasesApi` | 90.5% | ✅ OK |
| `Data/ClientData` | 91.6% | ✅ OK |
| `Models/Client` | 92.0% | ✅ OK |
| `Exceptions/ChipValidationException` | 94.4% | ✅ OK |
| `Data/WebhookData` | 95.4% | ✅ OK |
| `Enums/SendInstructionState` | 95.8% | ✅ OK |
| `Models/Payment` | 95.7% | ✅ OK |
| `Data/PurchaseData` | 96.0% | ✅ OK |
| `Exceptions/ChipApiException` | 96.2% | ✅ OK |
| `Testing/WebhookFactory` | 96.3% | ✅ OK |
| `Models/RecurringSchedule` | 96.8% | ✅ OK |
| `Data/SendLimitData` | 97.3% | ✅ OK |
| `Data/ProductData` | 97.4% | ✅ OK |
| `Data/PaymentData` | 97.3% | ✅ OK |
| `Data/BankAccountData` | 97.6% | ✅ OK |

## Full Coverage Files (100%) - NO ACTION NEEDED

- `Data/ClientDetailsData`
- `Data/CompanyStatementData`
- `Data/CurrencyConversionData`
- `Data/DashboardMetrics`
- `Data/EnrichedWebhookPayload`
- `Data/IssuerDetailsData`
- `Data/PurchaseDetailsData`
- `Data/RevenueMetrics`
- `Data/SendWebhookData`
- `Data/TransactionData`
- `Data/TransactionMetrics`
- `Data/WebhookHealth`
- `Data/WebhookResult`
- `Enums/BankAccountStatus`
- `Enums/ChargeStatus`
- `Enums/EWallet`
- `Enums/FpxBank`
- `Enums/FpxType`
- `Enums/RecurringInterval`
- `Enums/RecurringStatus`
- `Enums/WebhookEventType`
- `Events/BillingCancelled`
- `Events/PaymentRefunded`
- `Events/PayoutEvent`
- `Events/PayoutFailed`
- `Events/PayoutPending`
- `Events/PayoutSuccess`
- `Events/PurchaseCancelled`
- `Events/PurchaseCaptured`
- `Events/PurchaseCreated`
- `Events/PurchaseEvent`
- `Events/PurchasePaid`
- `Events/PurchasePaymentFailure`
- `Events/PurchasePendingCapture`
- `Events/PurchasePendingCharge`
- `Events/PurchasePendingExecute`
- `Events/PurchasePendingRecurringTokenDelete`
- `Events/PurchasePendingRefund`
- `Events/PurchasePendingRelease`
- `Events/PurchaseRecurringTokenDeleted`
- `Events/PurchaseReleased`
- `Events/RecurringChargeRetryScheduled`
- `Events/RecurringChargeSucceeded`
- `Events/RecurringScheduleCancelled`
- `Events/RecurringScheduleCreated`
- `Events/RecurringScheduleFailed`
- `Events/WebhookReceived`
- `Exceptions/NoRecurringTokenException`
- `Exceptions/WebhookVerificationException`
- `Facades/ChipSend`
- `Health/ChipGatewayCheck`
- `Http/Controllers/WebhookController`
- `Models/RecurringCharge`
- `Models/SendWebhook`
- `Services/Collect/AccountApi`
- `Services/Collect/ClientsApi`
- `Services/Collect/CollectApi`
- `Services/Collect/WebhooksApi`
- `Services/SubscriptionService`
- `Webhooks/ChipSignatureValidator`
- `Webhooks/ChipWebhookProfile`
- `Webhooks/Handlers/WebhookHandler`
- `Webhooks/WebhookEnricher`
- `Webhooks/WebhookRouter`
- `Webhooks/WebhookValidator`

---

## Testing Commands

```bash
# Run all chip tests
./vendor/bin/pest tests/src/Chip --configuration=.xml/chip.xml

# Run specific test file
./vendor/bin/pest tests/src/Chip/Unit/Commands/YourTest.php

# Run coverage for chip package
./vendor/bin/pest tests/src/Chip --coverage --configuration=.xml/chip.xml 2>&1 | tee /tmp/chip-coverage.txt
```

## Progress Log

| Date | Coverage | Change | Notes |
|------|----------|--------|-------|
| 2025-12-17 | 69.2% | - | Initial baseline |
