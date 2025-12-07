<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Testing;

use Illuminate\Support\Str;

/**
 * Fake CHIP client for testing purposes.
 *
 * This class provides mock responses for CHIP API calls,
 * allowing tests to run without real API credentials.
 */
class FakeChipClient
{
    /**
     * Storage for created clients.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $clients = [];

    /**
     * Storage for created purchases.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $purchases = [];

    /**
     * Storage for recurring tokens.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $recurringTokens = [];

    /**
     * Storage for webhooks.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $webhooks = [];

    /**
     * The brand ID for the fake client.
     */
    protected string $brandId;

    /**
     * Create a new fake CHIP client instance.
     */
    public function __construct(string $brandId = 'test-brand-id')
    {
        $this->brandId = $brandId;
    }

    /**
     * Get the brand ID.
     */
    public function getBrandId(): string
    {
        return $this->brandId;
    }

    /**
     * Create a new client.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createClient(array $data): array
    {
        $id = 'cli_'.Str::random(20);

        $client = array_merge([
            'id' => $id,
            'email' => $data['email'] ?? 'test@example.com',
            'phone' => $data['phone'] ?? null,
            'full_name' => $data['full_name'] ?? 'Test User',
            'personal_code' => $data['personal_code'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'country' => $data['country'] ?? 'MY',
            'city' => $data['city'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'shipping_street_address' => $data['shipping_street_address'] ?? null,
            'shipping_country' => $data['shipping_country'] ?? null,
            'shipping_city' => $data['shipping_city'] ?? null,
            'shipping_zip_code' => $data['shipping_zip_code'] ?? null,
            'legal_name' => $data['legal_name'] ?? null,
            'brand_id' => $this->brandId,
            'bank_account' => $data['bank_account'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'cc' => $data['cc'] ?? [],
            'bcc' => $data['bcc'] ?? [],
            'tax_number' => $data['tax_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'created_on' => now()->getTimestamp(),
            'updated_on' => now()->getTimestamp(),
        ], $data);

        $this->clients[$id] = $client;

        return $client;
    }

    /**
     * Get a client by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getClient(string $clientId): ?array
    {
        return $this->clients[$clientId] ?? null;
    }

    /**
     * List all clients.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listClients(array $filters = []): array
    {
        $clients = array_values($this->clients);

        if (isset($filters['email'])) {
            $clients = array_filter($clients, fn ($c) => $c['email'] === $filters['email']);
        }

        return [
            'results' => array_values($clients),
            'count' => count($clients),
        ];
    }

    /**
     * Update a client.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function updateClient(string $clientId, array $data): ?array
    {
        if (! isset($this->clients[$clientId])) {
            return null;
        }

        $this->clients[$clientId] = array_merge($this->clients[$clientId], $data);
        $this->clients[$clientId]['updated_on'] = now()->getTimestamp();

        return $this->clients[$clientId];
    }

    /**
     * Delete a client.
     */
    public function deleteClient(string $clientId): void
    {
        unset($this->clients[$clientId]);
    }

    /**
     * Create a new purchase.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPurchase(array $data): array
    {
        $id = 'pur_'.Str::random(20);

        $purchase = array_merge([
            'id' => $id,
            'client_id' => $data['client_id'] ?? null,
            'brand_id' => $this->brandId,
            'status' => 'created',
            'payment_method_whitelist' => $data['payment_method_whitelist'] ?? null,
            'is_recurring_token' => $data['is_recurring_token'] ?? false,
            'skip_capture' => $data['skip_capture'] ?? false,
            'purchase' => [
                'products' => $data['purchase']['products'] ?? [],
                'currency' => $data['purchase']['currency'] ?? 'MYR',
                'total' => $data['purchase']['total'] ?? 0,
                'total_override' => $data['purchase']['total_override'] ?? null,
                'language' => $data['purchase']['language'] ?? 'en',
                'notes' => $data['purchase']['notes'] ?? null,
            ],
            'client' => $data['client'] ?? null,
            'checkout_url' => 'https://gate.chip-in.asia/checkout/'.$id,
            'direct_post_url' => 'https://gate.chip-in.asia/direct-post/'.$id,
            'success_redirect' => $data['success_redirect'] ?? null,
            'failure_redirect' => $data['failure_redirect'] ?? null,
            'cancel_redirect' => $data['cancel_redirect'] ?? null,
            'success_callback' => $data['success_callback'] ?? null,
            'creator_agent' => $data['creator_agent'] ?? 'FakeChipClient',
            'reference' => $data['reference'] ?? null,
            'issued' => now()->toIso8601String(),
            'due' => $data['due'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'platform' => $data['platform'] ?? 'api',
            'send_receipt' => $data['send_receipt'] ?? false,
            'created_on' => now()->getTimestamp(),
            'updated_on' => now()->getTimestamp(),
        ], $data);

        $this->purchases[$id] = $purchase;

        return $purchase;
    }

    /**
     * Get a purchase by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getPurchase(string $purchaseId): ?array
    {
        return $this->purchases[$purchaseId] ?? null;
    }

    /**
     * Cancel a purchase.
     *
     * @return array<string, mixed>|null
     */
    public function cancelPurchase(string $purchaseId): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'cancelled';
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Refund a purchase.
     *
     * @return array<string, mixed>|null
     */
    public function refundPurchase(string $purchaseId, ?int $amount = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'refunded';
        $this->purchases[$purchaseId]['refunded_amount'] = $amount ?? $this->purchases[$purchaseId]['purchase']['total'];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Charge a purchase with a recurring token.
     *
     * @return array<string, mixed>|null
     */
    public function chargePurchase(string $purchaseId, string $recurringToken): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['recurring_token'] = $recurringToken;
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'card',
            'psp' => 'test-psp',
            'paid_on' => now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Capture a purchase.
     *
     * @return array<string, mixed>|null
     */
    public function capturePurchase(string $purchaseId, ?int $amount = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['captured_amount'] = $amount ?? $this->purchases[$purchaseId]['purchase']['total'];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Release a purchase.
     *
     * @return array<string, mixed>|null
     */
    public function releasePurchase(string $purchaseId): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'released';
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Mark a purchase as paid.
     *
     * @return array<string, mixed>|null
     */
    public function markPurchaseAsPaid(string $purchaseId, ?int $paidOn = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'manual',
            'paid_on' => $paidOn ?? now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Delete a recurring token from a purchase.
     */
    public function deleteRecurringToken(string $purchaseId): void
    {
        if (isset($this->purchases[$purchaseId])) {
            unset($this->purchases[$purchaseId]['recurring_token']);
        }
    }

    /**
     * Get available payment methods.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getPaymentMethods(array $filters = []): array
    {
        return [
            [
                'name' => 'fpx',
                'logo' => 'https://example.com/fpx.png',
                'available_banks' => [
                    ['name' => 'Maybank', 'code' => 'MBB0228'],
                    ['name' => 'CIMB Bank', 'code' => 'BCBB0235'],
                    ['name' => 'Public Bank', 'code' => 'PBB0233'],
                ],
            ],
            [
                'name' => 'card',
                'logo' => 'https://example.com/card.png',
            ],
            [
                'name' => 'ewallet',
                'logo' => 'https://example.com/ewallet.png',
            ],
        ];
    }

    /**
     * Get public key.
     */
    public function getPublicKey(): string
    {
        return "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0...(fake)\n-----END PUBLIC KEY-----";
    }

    /**
     * Get account balance.
     *
     * @return array<string, mixed>
     */
    public function getAccountBalance(): array
    {
        return [
            'balance' => 1000000,
            'currency' => 'MYR',
            'available' => 900000,
            'pending' => 100000,
        ];
    }

    /**
     * Get account turnover.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getAccountTurnover(array $filters = []): array
    {
        return [
            'total' => 5000000,
            'count' => 150,
            'currency' => 'MYR',
        ];
    }

    /**
     * Add a recurring token to a client.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function addRecurringToken(string $clientId, ?array $data = null): array
    {
        $tokenId = 'tok_'.Str::random(20);

        $token = array_merge([
            'id' => $tokenId,
            'recurring_token' => $tokenId,
            'type' => $data['type'] ?? 'card',
            'card_brand' => $data['card_brand'] ?? 'Visa',
            'brand' => $data['card_brand'] ?? 'Visa',
            'last_4' => $data['last_4'] ?? '4242',
            'card_last_4' => $data['last_4'] ?? '4242',
            'exp_month' => $data['exp_month'] ?? 12,
            'exp_year' => $data['exp_year'] ?? 2030,
            'client_id' => $clientId,
            'created_on' => now()->getTimestamp(),
        ], $data ?? []);

        if (! isset($this->recurringTokens[$clientId])) {
            $this->recurringTokens[$clientId] = [];
        }

        $this->recurringTokens[$clientId][$tokenId] = $token;

        return $token;
    }

    /**
     * List recurring tokens for a client.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listClientRecurringTokens(string $clientId): array
    {
        return array_values($this->recurringTokens[$clientId] ?? []);
    }

    /**
     * Get a specific recurring token for a client.
     *
     * @return array<string, mixed>|null
     */
    public function getClientRecurringToken(string $clientId, string $tokenId): ?array
    {
        return $this->recurringTokens[$clientId][$tokenId] ?? null;
    }

    /**
     * Delete a recurring token from a client.
     */
    public function deleteClientRecurringToken(string $clientId, string $tokenId): void
    {
        unset($this->recurringTokens[$clientId][$tokenId]);
    }

    /**
     * Create a webhook.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createWebhook(array $data): array
    {
        $id = 'whk_'.Str::random(20);

        $webhook = [
            'id' => $id,
            'url' => $data['url'] ?? '',
            'events' => $data['events'] ?? ['*'],
            'active' => $data['active'] ?? true,
            'brand_id' => $this->brandId,
            'created_on' => now()->getTimestamp(),
        ];

        $this->webhooks[$id] = $webhook;

        return $webhook;
    }

    /**
     * Get a webhook by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getWebhook(string $webhookId): ?array
    {
        return $this->webhooks[$webhookId] ?? null;
    }

    /**
     * Update a webhook.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public function updateWebhook(string $webhookId, array $data): ?array
    {
        if (! isset($this->webhooks[$webhookId])) {
            return null;
        }

        $this->webhooks[$webhookId] = array_merge($this->webhooks[$webhookId], $data);

        return $this->webhooks[$webhookId];
    }

    /**
     * Delete a webhook.
     */
    public function deleteWebhook(string $webhookId): void
    {
        unset($this->webhooks[$webhookId]);
    }

    /**
     * List all webhooks.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listWebhooks(array $filters = []): array
    {
        return [
            'results' => array_values($this->webhooks),
            'count' => count($this->webhooks),
        ];
    }

    /**
     * Simulate a payment being completed.
     *
     * This is useful for testing webhook handling.
     *
     * @return array<string, mixed>|null
     */
    public function simulatePaymentComplete(string $purchaseId, ?string $recurringToken = null): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $token = $recurringToken ?? 'tok_'.Str::random(20);

        $this->purchases[$purchaseId]['status'] = 'paid';
        $this->purchases[$purchaseId]['recurring_token'] = $token;
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'card',
            'psp' => 'test-psp',
            'paid_on' => now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Simulate a payment failure.
     *
     * @return array<string, mixed>|null
     */
    public function simulatePaymentFailure(string $purchaseId, string $reason = 'Payment declined'): ?array
    {
        if (! isset($this->purchases[$purchaseId])) {
            return null;
        }

        $this->purchases[$purchaseId]['status'] = 'failed';
        $this->purchases[$purchaseId]['payment'] = [
            'method' => 'card',
            'error' => $reason,
            'failed_on' => now()->getTimestamp(),
        ];
        $this->purchases[$purchaseId]['updated_on'] = now()->getTimestamp();

        return $this->purchases[$purchaseId];
    }

    /**
     * Get all stored clients.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * Get all stored purchases.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPurchases(): array
    {
        return $this->purchases;
    }

    /**
     * Get all stored recurring tokens.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getRecurringTokens(): array
    {
        return $this->recurringTokens;
    }

    /**
     * Reset all stored data.
     */
    public function reset(): void
    {
        $this->clients = [];
        $this->purchases = [];
        $this->recurringTokens = [];
        $this->webhooks = [];
    }
}
