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
 * PayPal Payouts API processor.
 */
final class PayPalProcessor implements PayoutProcessorInterface
{
    private string $clientId;

    private string $clientSecret;

    private string $apiUrl;

    private ?string $accessToken = null;

    public function __construct()
    {
        $this->clientId = config('affiliates.payouts.paypal.client_id', '');
        $this->clientSecret = config('affiliates.payouts.paypal.client_secret', '');
        $this->apiUrl = config('affiliates.payouts.paypal.sandbox', true)
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function process(AffiliatePayout $payout): PayoutResult
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return PayoutResult::failure('PayPal credentials not configured', 'PAYPAL_NOT_CONFIGURED');
        }

        $payoutMethod = $payout->affiliate->payoutMethods()
            ->where('type', 'paypal')
            ->where('is_default', true)
            ->first();

        if (! $payoutMethod) {
            return PayoutResult::failure('No PayPal account configured', 'NO_PAYPAL_ACCOUNT');
        }

        $paypalEmail = $payoutMethod->details['email'] ?? null;

        if (! $paypalEmail) {
            return PayoutResult::failure('PayPal email missing', 'INVALID_PAYPAL_ACCOUNT');
        }

        try {
            $token = $this->getAccessToken();

            if (! $token) {
                return PayoutResult::failure('Failed to authenticate with PayPal', 'PAYPAL_AUTH_FAILED');
            }

            $netAmount = $payout->amount_minor - $this->getFees($payout->amount_minor, $payout->currency);

            $response = Http::withToken($token)
                ->post("{$this->apiUrl}/v1/payments/payouts", [
                    'sender_batch_header' => [
                        'sender_batch_id' => $payout->id,
                        'email_subject' => 'You have a payout!',
                    ],
                    'items' => [
                        [
                            'recipient_type' => 'EMAIL',
                            'amount' => [
                                'value' => number_format($netAmount / 100, 2, '.', ''),
                                'currency' => mb_strtoupper($payout->currency),
                            ],
                            'sender_item_id' => $payout->id,
                            'receiver' => $paypalEmail,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $batchId = $data['batch_header']['payout_batch_id'] ?? null;

                return PayoutResult::success(
                    externalReference: $batchId,
                    metadata: ['payout_batch_id' => $batchId]
                );
            }

            $error = $response->json();

            return PayoutResult::failure(
                reason: $error['message'] ?? 'PayPal payout failed',
                code: $error['name'] ?? 'PAYPAL_ERROR'
            );
        } catch (Exception $e) {
            Log::error('PayPal payout error', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);

            return PayoutResult::failure($e->getMessage(), 'PAYPAL_EXCEPTION');
        }
    }

    public function getStatus(AffiliatePayout $payout): string
    {
        if (empty($payout->external_reference)) {
            return $payout->status;
        }

        try {
            $token = $this->getAccessToken();

            if (! $token) {
                return $payout->status;
            }

            $response = Http::withToken($token)
                ->get("{$this->apiUrl}/v1/payments/payouts/{$payout->external_reference}");

            if ($response->successful()) {
                $data = $response->json();
                $batchStatus = $data['batch_header']['batch_status'] ?? null;

                return match ($batchStatus) {
                    'SUCCESS' => 'completed',
                    'PENDING', 'PROCESSING' => 'processing',
                    'DENIED', 'CANCELED' => 'failed',
                    default => $payout->status,
                };
            }
        } catch (Exception $e) {
            Log::warning('Failed to get PayPal payout status', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payout->status;
    }

    public function cancel(AffiliatePayout $payout): bool
    {
        return false;
    }

    public function getEstimatedArrival(AffiliatePayout $payout): ?DateTimeInterface
    {
        return now();
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return min((int) ceil($amountMinor * 0.02), 100);
    }

    public function validateDetails(array $details): array
    {
        $errors = [];

        if (empty($details['email'])) {
            $errors['email'] = 'PayPal email is required';
        } elseif (! filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        return $errors;
    }

    public function getIdentifier(): string
    {
        return 'paypal';
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->apiUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $this->accessToken = $response->json('access_token');

                return $this->accessToken;
            }
        } catch (Exception $e) {
            Log::error('PayPal OAuth error', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
