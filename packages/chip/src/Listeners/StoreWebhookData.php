<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Models\Client;
use AIArmada\Chip\Models\Payment;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Store CHIP webhook data in the database.
 *
 * Maps webhook payload fields directly to database columns.
 * No fabrication - only stores what CHIP sends.
 */
final class StoreWebhookData
{
    public function handle(WebhookReceived $event): void
    {
        if (! config('chip.webhooks.store_data', true)) {
            return;
        }

        $payload = $event->payload;

        // Only process purchase-related webhooks
        if (($payload['type'] ?? null) !== 'purchase') {
            return;
        }

        if (empty($payload['id'])) {
            Log::warning('CHIP: No purchase ID in webhook payload');

            return;
        }

        $this->storePurchase($payload);
        $this->storeClient($payload);
        $this->storePayment($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storePurchase(array $payload): void
    {
        $owner = $this->resolveOwner();

        $purchase = Purchase::updateOrCreate(
            ['id' => $payload['id']],
            [
                // Core fields
                'type' => $payload['type'],
                'created_on' => $payload['created_on'],
                'updated_on' => $payload['updated_on'],

                // JSON objects
                'client' => $payload['client'] ?? [],
                'purchase' => $payload['purchase'] ?? [],
                'payment' => $payload['payment'] ?? null,
                'issuer_details' => $payload['issuer_details'] ?? [],
                'transaction_data' => $payload['transaction_data'] ?? [],
                'status_history' => $payload['status_history'] ?? [],
                'currency_conversion' => $payload['currency_conversion'] ?? null,
                'payment_method_whitelist' => $payload['payment_method_whitelist'] ?? null,
                'metadata' => $payload['purchase']['metadata'] ?? null,

                // UUID references
                'brand_id' => $payload['brand_id'],
                'company_id' => $payload['company_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'billing_template_id' => $payload['billing_template_id'] ?? null,
                'client_id' => $payload['client_id'] ?? null,

                // Status
                'status' => $payload['status'],
                'viewed_on' => $payload['viewed_on'] ?? null,

                // Flags
                'send_receipt' => $payload['send_receipt'] ?? false,
                'is_test' => $payload['is_test'] ?? false,
                'is_recurring_token' => $payload['is_recurring_token'] ?? false,
                'recurring_token' => $payload['recurring_token'] ?? null,
                'skip_capture' => $payload['skip_capture'] ?? false,
                'force_recurring' => $payload['force_recurring'] ?? false,
                'marked_as_paid' => $payload['marked_as_paid'] ?? false,

                // References
                'reference' => $payload['reference'] ?? null,
                'reference_generated' => $payload['reference_generated'] ?? null,
                'notes' => $payload['purchase']['notes'] ?? null,
                'issued' => $payload['issued'] ?? null,
                'due' => $payload['due'] ?? null,
                'order_id' => $payload['order_id'] ?? null,

                // Refund
                'refund_availability' => $payload['refund_availability'] ?? null,
                'refundable_amount' => $payload['refundable_amount'] ?? 0,

                // URLs
                'success_redirect' => $payload['success_redirect'] ?? null,
                'failure_redirect' => $payload['failure_redirect'] ?? null,
                'cancel_redirect' => $payload['cancel_redirect'] ?? null,
                'success_callback' => $payload['success_callback'] ?? null,
                'invoice_url' => $payload['invoice_url'] ?? null,
                'checkout_url' => $payload['checkout_url'] ?? null,
                'direct_post_url' => $payload['direct_post_url'] ?? null,

                // Platform
                'creator_agent' => $payload['creator_agent'] ?? null,
                'platform' => $payload['platform'] ?? null,
                'product' => $payload['product'] ?? null,
                'created_from_ip' => $payload['created_from_ip'] ?? null,
            ]
        );

        if ($owner !== null && ! $purchase->hasOwner()) {
            $purchase->assignOwner($owner)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeClient(array $payload): void
    {
        $owner = $this->resolveOwner();

        $clientId = $payload['client_id'] ?? null;
        $clientData = $payload['client'] ?? null;

        if (! $clientId || ! is_array($clientData) || empty($clientData['email'])) {
            return;
        }

        $client = Client::updateOrCreate(
            [
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
                'email' => $clientData['email'],
            ],
            [
                'id' => $clientId,
                'type' => 'client',
                'created_on' => $payload['created_on'],
                'updated_on' => $payload['updated_on'],
                'phone' => $clientData['phone'] ?? null,
                'full_name' => $clientData['full_name'] ?? null,
                'personal_code' => $clientData['personal_code'] ?? null,
                'street_address' => $clientData['street_address'] ?? null,
                'city' => $clientData['city'] ?? null,
                'zip_code' => $clientData['zip_code'] ?? null,
                'state' => $clientData['state'] ?? null,
                'country' => $clientData['country'] ?? null,
                'shipping_street_address' => $clientData['shipping_street_address'] ?? null,
                'shipping_city' => $clientData['shipping_city'] ?? null,
                'shipping_zip_code' => $clientData['shipping_zip_code'] ?? null,
                'shipping_state' => $clientData['shipping_state'] ?? null,
                'shipping_country' => $clientData['shipping_country'] ?? null,
                'cc' => $clientData['cc'] ?? null,
                'bcc' => $clientData['bcc'] ?? null,
                'legal_name' => $clientData['legal_name'] ?? null,
                'brand_name' => $clientData['brand_name'] ?? null,
                'registration_number' => $clientData['registration_number'] ?? null,
                'tax_number' => $clientData['tax_number'] ?? null,
                'bank_account' => $clientData['bank_account'] ?? null,
                'bank_code' => $clientData['bank_code'] ?? null,
            ]
        );

        if ($owner !== null && ! $client->hasOwner()) {
            $client->assignOwner($owner)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storePayment(array $payload): void
    {
        $owner = $this->resolveOwner();

        $paymentData = $payload['payment'] ?? null;

        if (! is_array($paymentData) || ! isset($paymentData['amount'])) {
            return;
        }

        // Payment object in webhook has no ID, generate one
        $paymentId = (string) Str::uuid();

        $payment = Payment::updateOrCreate(
            ['id' => $paymentId],
            [
                'purchase_id' => $payload['id'],
                'payment_type' => $paymentData['payment_type'] ?? null,
                'is_outgoing' => $paymentData['is_outgoing'] ?? false,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'],
                'net_amount' => $paymentData['net_amount'] ?? null,
                'fee_amount' => $paymentData['fee_amount'] ?? null,
                'pending_amount' => $paymentData['pending_amount'] ?? 0,
                'pending_unfreeze_on' => $paymentData['pending_unfreeze_on'] ?? null,
                'description' => $paymentData['description'] ?? null,
                'paid_on' => $paymentData['paid_on'] ?? null,
                'remote_paid_on' => $paymentData['remote_paid_on'] ?? null,
                'created_on' => $payload['created_on'],
                'updated_on' => $payload['updated_on'],
            ]
        );

        if ($owner !== null && ! $payment->hasOwner()) {
            $payment->assignOwner($owner)->save();
        }
    }

    private function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        return app(OwnerResolverInterface::class)->resolve();
    }
}
