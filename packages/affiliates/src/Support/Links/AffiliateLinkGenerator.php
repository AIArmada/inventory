<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Support\Links;

use Illuminate\Support\Arr;
use InvalidArgumentException;

final class AffiliateLinkGenerator
{
    /**
     * @param  array<string, string>  $params
     */
    public function generate(string $affiliateCode, string $url, array $params = [], ?int $ttlSeconds = null): string
    {
        $this->assertHostAllowed($url);

        $parameter = (string) config('affiliates.links.parameter', 'aff');
        $expires = $ttlSeconds === null
            ? (int) now()->addMinutes((int) config('affiliates.links.default_ttl_minutes', 60 * 24 * 7))->timestamp
            : (int) now()->addSeconds($ttlSeconds)->timestamp;

        $query = array_merge($params, [
            $parameter => $affiliateCode,
            'aff_exp' => $expires,
        ]);

        $signature = $this->sign($url, $query);
        $query['aff_sig'] = $signature;

        return $this->buildUrl($url, $query);
    }

    public function verify(string $url): bool
    {
        $parts = parse_url($url) ?: [];
        parse_str($parts['query'] ?? '', $query);

        $signature = Arr::pull($query, 'aff_sig');
        $expires = (int) ($query['aff_exp'] ?? 0);
        $query['aff_exp'] = $expires;

        if (! $signature || $expires < now()->timestamp) {
            return false;
        }

        $baseUrl = $this->stripQuery($url);

        return hash_equals($signature, $this->sign($baseUrl, $query));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function buildUrl(string $url, array $query): string
    {
        $parts = parse_url($url) ?: [];
        $existing = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $merged = array_merge($existing, $query);

        $base = $this->stripQuery($url);

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($merged);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function sign(string $url, array $query): string
    {
        $key = (string) config('affiliates.links.signing_key', config('app.key'));
        $parameter = (string) config('affiliates.links.parameter', 'aff');

        $payload = [
            'url' => $this->stripQuery($url),
            'aff' => $query[$parameter] ?? '',
            'exp' => $query['aff_exp'] ?? 0,
        ];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), $key);
    }

    private function stripQuery(string $url): string
    {
        return strtok($url, '?') ?: $url;
    }

    private function assertHostAllowed(string $url): void
    {
        $allowed = config('affiliates.links.allowed_hosts', []);

        if ($allowed === [] || ! is_array($allowed)) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host && in_array($host, $allowed, true)) {
            return;
        }

        throw new InvalidArgumentException('Link host is not allowed.');
    }
}
