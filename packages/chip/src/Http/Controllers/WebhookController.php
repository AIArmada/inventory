<?php

declare(strict_types=1);

namespace AIArmada\Chip\Http\Controllers;

use AIArmada\Chip\Data\BillingTemplateClientData;
use AIArmada\Chip\Data\PayoutData;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PayoutFailed;
use AIArmada\Chip\Events\PayoutPending;
use AIArmada\Chip\Events\PayoutSuccess;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchaseCaptured;
use AIArmada\Chip\Events\PurchaseCreated;
use AIArmada\Chip\Events\PurchaseHold;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePendingCapture;
use AIArmada\Chip\Events\PurchasePendingCharge;
use AIArmada\Chip\Events\PurchasePendingExecute;
use AIArmada\Chip\Events\PurchasePendingRecurringTokenDelete;
use AIArmada\Chip\Events\PurchasePendingRefund;
use AIArmada\Chip\Events\PurchasePendingRelease;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseRecurringTokenDeleted;
use AIArmada\Chip\Events\PurchaseReleased;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Support\ChipWebhookOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming CHIP webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owner = ChipWebhookOwnerResolver::resolveFromPayload($payload);

            if ($owner === null) {
                Log::channel(config('chip.logging.channel', 'stack'))
                    ->error('CHIP webhook received but no owner could be resolved for brand_id', [
                        'event_type' => $eventType,
                        'brand_id' => $payload['brand_id'] ?? null,
                    ]);

                return response()->json([
                    'error' => 'Owner resolution failed',
                ], 500);
            }

            return OwnerContext::withOwner($owner, fn (): JsonResponse => $this->handleScoped($eventType, $payload));
        }

        return $this->handleScoped($eventType, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleScoped(string $eventType, array $payload): JsonResponse
    {
        // Dispatch the generic WebhookReceived event
        WebhookReceived::dispatch(
            $eventType,
            $payload,
            $this->extractPurchase($payload),
            $this->extractPayout($payload),
            $this->extractBillingTemplateClient($payload),
        );

        // Dispatch the specific typed event
        $this->dispatchTypedEvent($eventType, $payload);

        return response()->json([
            'status' => 'ok',
            'event_type' => $eventType,
        ]);

    }

    /**
     * Dispatch the appropriate typed event based on event type.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchTypedEvent(string $eventType, array $payload): void
    {
        $type = WebhookEventType::fromString($eventType);

        if ($type === null) {
            Log::channel(config('chip.logging.channel', 'stack'))
                ->warning('Unknown CHIP webhook event type', [
                    'event_type' => $eventType,
                ]);

            return;
        }

        match ($type) {
            // Purchase lifecycle events
            WebhookEventType::PurchaseCreated => PurchaseCreated::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePaid => PurchasePaid::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePaymentFailure => PurchasePaymentFailure::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCancelled => PurchaseCancelled::dispatch($this->extractPurchase($payload), $payload),

            // Pending events
            WebhookEventType::PurchasePendingExecute => PurchasePendingExecute::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingCharge => PurchasePendingCharge::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingCapture => PurchasePendingCapture::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRelease => PurchasePendingRelease::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRefund => PurchasePendingRefund::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePendingRecurringTokenDelete => PurchasePendingRecurringTokenDelete::dispatch($this->extractPurchase($payload), $payload),

            // Authorization/capture events
            WebhookEventType::PurchaseHold => PurchaseHold::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseCaptured => PurchaseCaptured::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchaseReleased => PurchaseReleased::dispatch($this->extractPurchase($payload), $payload),
            WebhookEventType::PurchasePreauthorized => PurchasePreauthorized::dispatch($this->extractPurchase($payload), $payload),

            // Recurring token events
            WebhookEventType::PurchaseRecurringTokenDeleted => PurchaseRecurringTokenDeleted::dispatch($this->extractPurchase($payload), $payload),

            // Subscription events
            WebhookEventType::PurchaseSubscriptionChargeFailure => PurchaseSubscriptionChargeFailure::dispatch($this->extractPurchase($payload), $payload),

            // Refund events
            WebhookEventType::PaymentRefunded => PaymentRefunded::dispatch($this->extractPurchase($payload), $payload),

            // Billing events
            WebhookEventType::BillingTemplateClientSubscriptionBillingCancelled => BillingCancelled::dispatch($this->extractBillingTemplateClient($payload), $payload),

            // Payout events
            WebhookEventType::PayoutPending => PayoutPending::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutFailed => PayoutFailed::dispatch($this->extractPayout($payload), $payload),
            WebhookEventType::PayoutSuccess => PayoutSuccess::dispatch($this->extractPayout($payload), $payload),
        };
    }

    /**
     * Extract Purchase data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractPurchase(array $payload): ?PurchaseData
    {
        $type = $payload['type'] ?? '';

        if ($type === 'purchase' || str_starts_with($payload['event_type'] ?? '', 'purchase.') || str_starts_with($payload['event_type'] ?? '', 'payment.')) {
            return PurchaseData::from($payload);
        }

        return null;
    }

    /**
     * Extract Payout data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractPayout(array $payload): ?PayoutData
    {
        $type = $payload['type'] ?? '';

        if ($type === 'payout' || str_starts_with($payload['event_type'] ?? '', 'payout.')) {
            return PayoutData::from($payload);
        }

        return null;
    }

    /**
     * Extract BillingTemplateClientData data object from payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractBillingTemplateClient(array $payload): ?BillingTemplateClientData
    {
        $type = $payload['type'] ?? '';

        if ($type === 'billing_template_client' || str_starts_with($payload['event_type'] ?? '', 'billing_template_client.')) {
            return BillingTemplateClientData::from($payload);
        }

        return null;
    }
}
