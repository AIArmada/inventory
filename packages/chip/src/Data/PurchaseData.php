<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Akaunting\Money\Money;
use Carbon\Carbon;

class PurchaseData
{
    public function __construct(
        public readonly string $id, // UUID as string
        public readonly string $type,
        public readonly int $created_on, // Unix timestamp from API
        public readonly int $updated_on, // Unix timestamp from API
        public readonly ClientDetailsData $client,
        public readonly PurchaseDetailsData $purchase,
        public readonly string $brand_id, // UUID as string
        public readonly ?PaymentData $payment,
        public readonly IssuerDetailsData $issuer_details,
        public readonly TransactionData $transaction_data,
        public readonly string $status,
        /** @var array<string, mixed> */
        public readonly array $status_history,
        public readonly ?int $viewed_on, // Unix timestamp or null
        public readonly ?string $company_id, // UUID as string or null
        public readonly bool $is_test,
        public readonly ?string $user_id, // UUID as string or null
        public readonly ?string $billing_template_id, // UUID as string or null
        public readonly ?string $client_id, // UUID as string or null
        public readonly bool $send_receipt,
        public readonly bool $is_recurring_token,
        public readonly ?string $recurring_token, // UUID as string or null
        public readonly bool $skip_capture,
        public readonly bool $force_recurring,
        public readonly string $reference_generated,
        public readonly ?string $reference,
        public readonly ?string $notes,
        public readonly ?string $issued, // ISO 8601 date string
        public readonly ?int $due, // Unix timestamp or null
        public readonly string $refund_availability,
        public readonly Money $refundable_amount,
        public readonly ?CurrencyConversionData $currency_conversion,
        /** @var array<string, mixed> */
        public readonly array $payment_method_whitelist,
        public readonly ?string $success_redirect,
        public readonly ?string $failure_redirect,
        public readonly ?string $cancel_redirect,
        public readonly ?string $success_callback,
        public readonly ?string $creator_agent,
        public readonly string $platform,
        public readonly string $product,
        public readonly ?string $created_from_ip,
        public readonly ?string $invoice_url,
        public readonly ?string $checkout_url,
        public readonly ?string $direct_post_url,
        public readonly bool $marked_as_paid,
        public readonly ?string $order_id,
        /** @var array<int, mixed> */
        public readonly array $upsell_campaigns,
        public readonly ?string $referral_campaign_id,
        public readonly ?string $referral_code,
        public readonly mixed $referral_code_details, // Can be null or object
        public readonly ?string $referral_code_generated,
        public readonly mixed $retain_level_details, // Can be null or object
        public readonly bool $can_retrieve,
        public readonly bool $can_chargeback,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Sanitize UUID fields - PostgreSQL UUID columns cannot accept empty strings
        $data = self::sanitizeUuidFields($data);

        // Handle both API response structure and simplified test data

        // For timestamps, convert string dates to timestamps if needed
        $created_on = $data['created_on'] ?? null;
        if (is_string($created_on)) {
            $created_on = strtotime($created_on) ?: null;
        }
        if ($created_on === null && isset($data['created_at'])) {
            $created_at = $data['created_at'];
            $created_on = is_string($created_at) ? strtotime($created_at) : $created_at;
        }
        $created_on = $created_on ?? time();

        $updated_on = $data['updated_on'] ?? null;
        if (is_string($updated_on)) {
            $updated_on = strtotime($updated_on) ?: null;
        }
        if ($updated_on === null && isset($data['updated_at'])) {
            $updated_at = $data['updated_at'];
            $updated_on = is_string($updated_at) ? strtotime($updated_at) : $updated_at;
        }
        $updated_on = $updated_on ?? time();

        // Handle client data
        $client = null;
        if (isset($data['client'])) {
            $client = ClientDetailsData::fromArray($data['client']);
        } else {
            // Create minimal client for test data
            $client = ClientDetailsData::fromArray([]);
        }

        // Handle purchase data
        $purchase = null;
        if (isset($data['purchase'])) {
            $purchase = PurchaseDetailsData::fromArray($data['purchase']);
        } else {
            // Create purchase from flat test data structure
            $purchase = PurchaseDetailsData::fromArray([
                'total' => $data['amount_in_cents'] ?? 0,
                'currency' => $data['currency'] ?? 'MYR',
                'products' => [],
                'metadata' => $data['metadata'] ?? null,
            ]);
        }

        return new self(
            id: $data['id'],
            type: $data['type'] ?? 'purchase',
            created_on: $created_on,
            updated_on: $updated_on,
            client: $client,
            purchase: $purchase,
            brand_id: $data['brand_id'] ?? '',
            payment: isset($data['payment']) ? PaymentData::fromArray($data['payment']) : null,
            issuer_details: isset($data['issuer_details']) ? IssuerDetailsData::fromArray($data['issuer_details']) : IssuerDetailsData::fromArray(['legal_name' => '']),
            transaction_data: isset($data['transaction_data']) ? TransactionData::fromArray($data['transaction_data']) : TransactionData::fromArray(['payment_method' => '', 'attempts' => []]),
            status: $data['status'] ?? 'created',
            status_history: $data['status_history'] ?? [],
            viewed_on: $data['viewed_on'] ?? null,
            company_id: $data['company_id'] ?? null,
            is_test: $data['is_test'] ?? true,
            user_id: $data['user_id'] ?? null,
            billing_template_id: $data['billing_template_id'] ?? null,
            client_id: $data['client_id'] ?? null,
            send_receipt: $data['send_receipt'] ?? false,
            is_recurring_token: $data['is_recurring_token'] ?? ($data['is_recurring'] ?? false),
            recurring_token: $data['recurring_token'] ?? null,
            skip_capture: $data['skip_capture'] ?? false,
            force_recurring: $data['force_recurring'] ?? false,
            reference_generated: $data['reference_generated'] ?? ($data['reference'] ?? ''),
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            issued: $data['issued'] ?? null,
            due: $data['due'] ?? null,
            refund_availability: $data['refund_availability'] ?? 'all',
            refundable_amount: Money::{$purchase->currency}($data['refundable_amount'] ?? 0),
            currency_conversion: isset($data['currency_conversion']) ? CurrencyConversionData::fromArray($data['currency_conversion']) : null,
            payment_method_whitelist: $data['payment_method_whitelist'] ?? [],
            success_redirect: $data['success_redirect'] ?? null,
            failure_redirect: $data['failure_redirect'] ?? null,
            cancel_redirect: $data['cancel_redirect'] ?? null,
            success_callback: $data['success_callback'] ?? null,
            creator_agent: $data['creator_agent'] ?? null,
            platform: $data['platform'] ?? 'api',
            product: $data['product'] ?? 'purchases',
            created_from_ip: $data['created_from_ip'] ?? null,
            invoice_url: $data['invoice_url'] ?? null,
            checkout_url: $data['checkout_url'] ?? null,
            direct_post_url: $data['direct_post_url'] ?? null,
            marked_as_paid: $data['marked_as_paid'] ?? false,
            order_id: $data['order_id'] ?? null,
            upsell_campaigns: $data['upsell_campaigns'] ?? [],
            referral_campaign_id: $data['referral_campaign_id'] ?? null,
            referral_code: $data['referral_code'] ?? null,
            referral_code_details: $data['referral_code_details'] ?? null,
            referral_code_generated: $data['referral_code_generated'] ?? null,
            retain_level_details: $data['retain_level_details'] ?? null,
            can_retrieve: $data['can_retrieve'] ?? false,
            can_chargeback: $data['can_chargeback'] ?? false,
        );
    }

    /**
     * Get the checkout URL for this purchase.
     */
    public function getCheckoutUrl(): ?string
    {
        return $this->checkout_url;
    }

    /**
     * Get the currency code for this purchase.
     */
    public function getCurrency(): string
    {
        return $this->purchase->currency;
    }

    /**
     * Get the client ID associated with this purchase.
     */
    public function getClientId(): ?string
    {
        return $this->client_id;
    }

    /**
     * Check if this purchase uses a recurring token.
     */
    public function isRecurring(): bool
    {
        return $this->is_recurring_token;
    }

    /**
     * Get the purchase metadata.
     *
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        if ($this->purchase->metadata === null || count($this->purchase->metadata) === 0) {
            return null;
        }

        return $this->purchase->metadata;
    }

    /**
     * Get the total amount as Money object.
     */
    public function getAmount(): Money
    {
        return $this->purchase->total;
    }

    /**
     * Get the total amount in cents for API communication.
     */
    public function getAmountInCents(): int
    {
        return $this->purchase->getTotalInCents();
    }

    public function getCreatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->created_on);
    }

    public function getUpdatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->updated_on);
    }

    public function getViewedAt(): ?Carbon
    {
        return $this->viewed_on ? Carbon::createFromTimestamp($this->viewed_on) : null;
    }

    public function getDueDate(): ?Carbon
    {
        return $this->due ? Carbon::createFromTimestamp($this->due) : null;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOnHold(): bool
    {
        return $this->status === 'hold';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending_execute', 'pending_capture', 'pending_charge', 'pending_refund', 'pending_release']);
    }

    public function hasError(): bool
    {
        return in_array($this->status, ['error', 'blocked']);
    }

    public function canBeRefunded(): bool
    {
        return in_array($this->refund_availability, ['all', 'full_only', 'partial_only']);
    }

    public function canBePartiallyRefunded(): bool
    {
        return in_array($this->refund_availability, ['all', 'partial_only']);
    }

    public function canRetrieve(): bool
    {
        return $this->can_retrieve;
    }

    public function canChargeback(): bool
    {
        return $this->can_chargeback;
    }

    public function hasUpsellCampaigns(): bool
    {
        return count($this->upsell_campaigns) > 0;
    }

    public function hasReferralCode(): bool
    {
        return $this->referral_code !== null;
    }

    /**
     * Get the refundable amount as Money object.
     */
    public function getRefundableAmount(): Money
    {
        return $this->refundable_amount;
    }

    /**
     * Get the refundable amount in cents for API communication.
     */
    public function getRefundableAmountInCents(): int
    {
        return (int) $this->refundable_amount->getAmount();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
            'client' => $this->client->toArray(),
            'purchase' => $this->purchase->toArray(),
            'brand_id' => $this->brand_id,
            'payment' => $this->payment?->toArray(),
            'issuer_details' => $this->issuer_details->toArray(),
            'transaction_data' => $this->transaction_data->toArray(),
            'status' => $this->status,
            'status_history' => $this->status_history,
            'viewed_on' => $this->viewed_on,
            'company_id' => $this->company_id,
            'is_test' => $this->is_test,
            'user_id' => $this->user_id,
            'billing_template_id' => $this->billing_template_id,
            'client_id' => $this->client_id,
            'send_receipt' => $this->send_receipt,
            'is_recurring_token' => $this->is_recurring_token,
            'recurring_token' => $this->recurring_token,
            'skip_capture' => $this->skip_capture,
            'force_recurring' => $this->force_recurring,
            'reference_generated' => $this->reference_generated,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'issued' => $this->issued,
            'due' => $this->due,
            'refund_availability' => $this->refund_availability,
            'refundable_amount' => $this->getRefundableAmountInCents(),
            'currency_conversion' => $this->currency_conversion?->toArray(),
            'payment_method_whitelist' => $this->payment_method_whitelist,
            'success_redirect' => $this->success_redirect,
            'failure_redirect' => $this->failure_redirect,
            'cancel_redirect' => $this->cancel_redirect,
            'success_callback' => $this->success_callback,
            'creator_agent' => $this->creator_agent,
            'platform' => $this->platform,
            'product' => $this->product,
            'created_from_ip' => $this->created_from_ip,
            'invoice_url' => $this->invoice_url,
            'checkout_url' => $this->checkout_url,
            'direct_post_url' => $this->direct_post_url,
            'marked_as_paid' => $this->marked_as_paid,
            'order_id' => $this->order_id,
            'upsell_campaigns' => $this->upsell_campaigns,
            'referral_campaign_id' => $this->referral_campaign_id,
            'referral_code' => $this->referral_code,
            'referral_code_details' => $this->referral_code_details,
            'referral_code_generated' => $this->referral_code_generated,
            'retain_level_details' => $this->retain_level_details,
            'can_retrieve' => $this->can_retrieve,
            'can_chargeback' => $this->can_chargeback,
        ];
    }

    /**
     * Sanitize UUID fields to handle empty strings from CHIP API.
     * PostgreSQL UUID columns cannot accept empty strings, only valid UUIDs or NULL.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function sanitizeUuidFields(array $data): array
    {
        $uuidFields = [
            'company_id',
            'user_id',
            'billing_template_id',
            'client_id',
            'recurring_token',
            'referral_campaign_id',
        ];

        foreach ($uuidFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
