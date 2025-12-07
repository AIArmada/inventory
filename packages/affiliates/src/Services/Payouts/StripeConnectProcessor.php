<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Models\AffiliatePayout;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Connect payout processor.
 */
final class StripeConnectProcessor implements PayoutProcessorInterface
{
    private string $apiKey;

    private string $apiUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->apiKey = config('affiliates.payouts.stripe.secret_key', '');
    }

    public function process(AffiliatePayout $payout): PayoutResult
    {
        if (empty($this->apiKey)) {
            return PayoutResult::failure('Stripe API key not configured', 'STRIPE_NOT_CONFIGURED');
        }

        $payoutMethod = $payout->affiliate->payoutMethods()
            ->where('type', 'stripe_connect')
            ->where('is_default', true)
            ->first();

        if (! $payoutMethod) {
            return PayoutResult::failure('No Stripe Connect account configured', 'NO_STRIPE_ACCOUNT');
        }

        $stripeAccountId = $payoutMethod->details['stripe_account_id'] ?? null;

        if (! $stripeAccountId) {
            return PayoutResult::failure('Stripe account ID missing', 'INVALID_STRIPE_ACCOUNT');
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->asForm()
                ->post("{$this->apiUrl}/transfers", [
                    'amount' => $payout->amount_minor - $this->getFees($payout->amount_minor, $payout->currency),
                    'currency' => mb_strtolower($payout->currency),
                    'destination' => $stripeAccountId,
                    'transfer_group' => $payout->batch_id ?? $payout->id,
                    'metadata' => [
                        'payout_id' => $payout->id,
                        'affiliate_id' => $payout->owner_id,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return PayoutResult::success(
                    externalReference: $data['id'],
                    metadata: ['transfer_id' => $data['id']]
                );
            }

            $error = $response->json();

            return PayoutResult::failure(
                reason: $error['error']['message'] ?? 'Stripe transfer failed',
                code: $error['error']['code'] ?? 'STRIPE_ERROR'
            );
        } catch (Exception $e) {
            Log::error('Stripe payout error', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);

            return PayoutResult::failure($e->getMessage(), 'STRIPE_EXCEPTION');
        }
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        if (empty($payout->external_reference) || empty($this->apiKey)) {
            return $payout->status;
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->get("{$this->apiUrl}/transfers/{$payout->external_reference}");

            if ($response->successful()) {
                return 'completed';
            }
        } catch (Exception $e) {
            Log::warning('Failed to get Stripe transfer status', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payout->status;
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        if (empty($payout->external_reference) || empty($this->apiKey)) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, '')
                ->asForm()
                ->post("{$this->apiUrl}/transfers/{$payout->external_reference}/reversals");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to cancel Stripe transfer', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return now()->addDays(2);
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        $percentFee = (int) ceil($amountMinor * 0.0025);
        $flatFee = 25;

        return $percentFee + $flatFee;
    }

    public function validateDetails(array $details): array
    {
        $errors = [];

        if (empty($details['stripe_account_id'])) {
            $errors['stripe_account_id'] = 'Stripe account ID is required';
        } elseif (! str_starts_with($details['stripe_account_id'], 'acct_')) {
            $errors['stripe_account_id'] = 'Invalid Stripe account ID format';
        }

        return $errors;
    }

    public function getIdentifier(): string
    {
        return 'stripe_connect';
    }
}
