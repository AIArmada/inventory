<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Webhooks;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $type, array $payload): void
    {
        if (! (bool) config('affiliates.events.dispatch_webhooks', false)) {
            return;
        }

        $endpoints = Arr::wrap(config("affiliates.webhooks.endpoints.{$type}", []));
        $headers = (array) config('affiliates.webhooks.headers', []);
        $secret = config('affiliates.webhooks.signature_secret');

        if (! is_string($secret) || $secret === '') {
            $secret = $headers['X-Affiliates-Signature'] ?? config('affiliates.webhooks.headers.X-Affiliates-Signature');
        }

        unset($headers['X-Affiliates-Signature']);

        foreach ($endpoints as $url) {
            $trimmed = mb_trim((string) $url);

            if ($trimmed === '') {
                continue;
            }

            $body = [
                'type' => $type,
                'id' => (string) Str::uuid(),
                'data' => $payload,
                'sent_at' => now()->toIso8601String(),
            ];

            $signature = $this->sign($body, is_string($secret) ? $secret : null);

            Http::withHeaders(array_merge($headers, [
                'X-Affiliates-Webhook-Signature' => $signature ?? '',
            ]))->asJson()->post($trimmed, $body);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sign(array $body, ?string $secret): ?string
    {
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        return hash_hmac('sha256', json_encode($body, JSON_THROW_ON_ERROR), $secret);
    }
}
