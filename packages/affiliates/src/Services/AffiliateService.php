<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Exceptions\AffiliateNotFoundException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\Cart\Cart;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Stringable;

final class AffiliateService
{
    public function __construct(
        private readonly CommissionCalculator $commissionCalculator,
        private readonly OwnerResolverInterface $ownerResolver,
        private readonly Dispatcher $events,
        private readonly WebhookDispatcher $webhooks,
        private readonly AttributionModel $attributionModel
    ) {}

    public function query(): Builder
    {
        $query = Affiliate::query();

        $owner = $this->resolveOwner();

        if ($owner) {
            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        }

        return $query;
    }

    public function findByCode(string $code): ?Affiliate
    {
        $normalized = $this->normalizeCode($code);
        $query = $this->query();
        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        /** @var Affiliate|null */
        return $query
            ->when(
                $driver === 'pgsql',
                fn ($q) => $q->whereRaw('code ILIKE ?', [$normalized]),
                fn ($q) => $q->whereRaw('LOWER(code) = ?', [mb_strtolower($normalized)])
            )
            ->first();
    }

    public function findByDefaultVoucherCode(string $voucherCode): ?Affiliate
    {
        $normalized = $this->normalizeCode($voucherCode);
        $query = $this->query();
        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        /** @var Affiliate|null */
        return $query
            ->when(
                $driver === 'pgsql',
                fn ($q) => $q->whereRaw('default_voucher_code ILIKE ?', [$normalized]),
                fn ($q) => $q->whereRaw('LOWER(default_voucher_code) = ?', [mb_strtolower($normalized)])
            )
            ->first();
    }

    public function attachToCartByCode(string $code, Cart $cart, array $context = []): ?AffiliateAttributionData
    {
        $affiliate = $this->findByCode($code);

        if (! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        return $this->attachAffiliate($affiliate, $cart, $context);
    }

    public function attachAffiliate(Affiliate $affiliate, Cart $cart, array $context = []): ?AffiliateAttributionData
    {
        if (! $affiliate->isActive()) {
            throw new AffiliateNotFoundException("Affiliate {$affiliate->code} is not active.");
        }

        if ($this->isSelfReferral($affiliate)) {
            return null;
        }

        $identifier = $cart->getIdentifier();
        $instance = $cart->instance();

        /** @var AffiliateAttribution|null $attribution */
        $attribution = AffiliateAttribution::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->where('cart_identifier', $identifier)
            ->where('cart_instance', $instance)
            ->first();

        $payload = $this->buildAttributionPayload($affiliate, $cart, $context);

        if (! $attribution && isset($payload['cookie_value'])) {
            $attribution = $this->findAttributionByCookie((string) $payload['cookie_value']);
        }

        if ($attribution) {
            $this->fillAttribution($attribution, $payload);
        } else {
            if (! isset($payload['cart_instance']) || $payload['cart_instance'] === null) {
                $payload['cart_instance'] = 'default';
            }

            $attribution = new AffiliateAttribution($payload);
            $attribution->first_seen_at = now();
        }

        $attribution->last_seen_at = now();
        if (isset($payload['cookie_value'])) {
            $attribution->last_cookie_seen_at = now();
        }
        $attribution->expires_at = $payload['expires_at'];
        $attribution->save();
        $this->recordTouchpoint($attribution, $affiliate, $payload);
        $this->pruneAttributionOverflow($identifier, $instance, $affiliate->owner_type, $affiliate->owner_id);

        if (config('affiliates.cart.persist_metadata', true)) {
            $this->persistCartMetadata($cart, $affiliate, $attribution);
        }

        if ($this->shouldDispatch('dispatch_attributed')) {
            $this->events?->dispatch(
                new AffiliateAttributed(
                    AffiliateData::fromModel($affiliate),
                    AffiliateAttributionData::fromModel($attribution)
                )
            );
        }

        $attributionData = AffiliateAttributionData::fromModel($attribution);

        if ($this->shouldDispatch('dispatch_webhooks')) {
            $this->webhooks->dispatch('attribution', $attributionData->toArray());
        }

        return $attributionData;
    }

    public function attachAffiliateFromCookie(Cart $cart, string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        $attribution = $this->findAttributionByCookie($cookieValue);

        if (! $attribution || ! $attribution->affiliate || ! $attribution->affiliate->isActive()) {
            return null;
        }

        $context = array_merge([
            'cookie_value' => $cookieValue,
            'source' => $context['source'] ?? $attribution->source,
            'medium' => $context['medium'] ?? $attribution->medium,
            'campaign' => $context['campaign'] ?? $attribution->campaign,
            'term' => $context['term'] ?? $attribution->term,
            'content' => $context['content'] ?? $attribution->content,
            'landing_url' => $context['landing_url'] ?? $attribution->landing_url,
            'referrer_url' => $context['referrer_url'] ?? $attribution->referrer_url,
        ], $context);

        return $this->attachAffiliate($attribution->affiliate, $cart, $context);
    }

    public function trackVisitByCode(string $code, array $context = [], ?string $cookieValue = null): ?AffiliateAttributionData
    {
        $affiliate = $this->findByCode($code);

        if (! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        if ($this->isRateLimited($affiliate, $context)) {
            return null;
        }

        if ($this->isSelfReferral($affiliate)) {
            return null;
        }

        if ($this->isFingerprintBlocked($affiliate, $context)) {
            return null;
        }

        $attribution = $this->storeCookieAttribution($affiliate, $context, $cookieValue);

        return AffiliateAttributionData::fromModel($attribution);
    }

    public function touchCookieAttribution(string $cookieValue, array $context = []): ?AffiliateAttributionData
    {
        $attribution = $this->findAttributionByCookie($cookieValue);

        if (! $attribution) {
            return null;
        }

        $attribution->loadMissing('affiliate');

        if (! $attribution->affiliate) {
            return null;
        }

        $payload = $this->buildAttributionPayload($attribution->affiliate, null, $context);
        $this->fillAttribution($attribution, $payload);

        $attribution->last_cookie_seen_at = now();
        $attribution->save();

        return AffiliateAttributionData::fromModel($attribution);
    }

    public function detachFromCart(Cart $cart): void
    {
        $cart->removeMetadata($this->metadataKey());
    }

    public function getAttachedAffiliate(Cart $cart): ?AffiliateData
    {
        $payload = $this->readCartMetadata($cart);

        if (! $payload) {
            return null;
        }

        $affiliate = null;

        if (isset($payload['affiliate_id'])) {
            /** @var Affiliate|null $affiliate */
            $affiliate = $this->query()->find($payload['affiliate_id']);
        }

        if (! $affiliate && isset($payload['affiliate_code'])) {
            $affiliate = $this->findByCode((string) $payload['affiliate_code']);
        }

        if (! $affiliate) {
            return null;
        }

        return AffiliateData::fromModel($affiliate);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordConversion(Cart $cart, array $payload = []): ?AffiliateConversionData
    {
        $metadata = $this->readCartMetadata($cart);

        if (! $metadata) {
            return null;
        }

        $affiliate = $this->resolveAffiliateFromMetadata($metadata);

        if (! $affiliate) {
            return null;
        }

        $attribution = $this->resolveAttributionFromMetadata($metadata);

        $subtotalMinor = $this->resolveMinorAmount($payload['subtotal'] ?? null, fn () => $cart->subtotal()->getAmount());
        $totalMinor = $this->resolveMinorAmount($payload['total'] ?? null, fn () => $cart->total()->getAmount());

        $commissionMinor = $this->resolveMinorAmount(
            $payload['commission'] ?? null,
            fn () => $this->commissionCalculator->calculate($affiliate, $subtotalMinor ?? $totalMinor ?? 0)
        );

        $status = config('affiliates.commissions.default_status', ConversionStatus::Pending->value);
        $statusEnum = ConversionStatus::tryFrom($status) ?? ConversionStatus::Pending;
        $autoApprove = config('affiliates.commissions.auto_approve', false);

        $touches = $attribution?->touchpoints()->get() ?? collect();
        $weights = $this->attributionModel->distribute($touches);

        if ($weights === []) {
            $weights = [$affiliate->getKey() => 1.0];
        }

        $conversions = [];

        foreach ($weights as $affiliateId => $weight) {
            $weight = max(0, (float) $weight);
            $portionCommission = (int) round(($commissionMinor ?? 0) * $weight);
            $portionRevenue = (int) round(($totalMinor ?? 0) * $weight);
            $beneficiary = $affiliateId === $affiliate->getKey()
                ? $affiliate
                : $this->query()->find($affiliateId);

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $beneficiary?->getKey() ?? $affiliateId,
                'affiliate_code' => $beneficiary?->code ?? $affiliate->code,
                'affiliate_attribution_id' => $attribution?->getKey(),
                'cart_identifier' => $cart->getIdentifier(),
                'cart_instance' => $cart->instance(),
                'voucher_code' => $metadata['voucher_code'] ?? null,
                'order_reference' => $payload['order_reference'] ?? null,
                'subtotal_minor' => $subtotalMinor ?? 0,
                'total_minor' => $portionRevenue,
                'commission_minor' => $portionCommission,
                'commission_currency' => $payload['commission_currency'] ?? $affiliate->currency,
                'status' => $autoApprove ? ConversionStatus::Approved : $statusEnum,
                'channel' => $payload['channel'] ?? null,
                'metadata' => array_merge($payload['metadata'] ?? [], ['weight' => $weight]),
                'owner_type' => $beneficiary?->owner_type ?? $affiliate->owner_type,
                'owner_id' => $beneficiary?->owner_id ?? $affiliate->owner_id,
                'occurred_at' => $payload['occurred_at'] ?? now(),
                'approved_at' => $autoApprove ? now() : null,
            ]);

            $conversionData = AffiliateConversionData::fromModel($conversion);
            $conversions[] = $conversionData;

            if ($this->shouldDispatch('dispatch_conversion')) {
                $this->events?->dispatch(new AffiliateConversionRecorded($conversionData));
            }

            if ($this->shouldDispatch('dispatch_webhooks')) {
                $this->webhooks->dispatch('conversion', $conversionData->toArray());
            }
        }

        $this->applyMultiLevelCommissions($conversions, $autoApprove, $statusEnum, $attribution?->getKey());

        return $conversions[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordTouchpoint(
        AffiliateAttribution $attribution,
        Affiliate $affiliate,
        array $payload
    ): void {
        AffiliateTouchpoint::create([
            'affiliate_attribution_id' => $attribution->getKey(),
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'source' => $payload['source'] ?? null,
            'medium' => $payload['medium'] ?? null,
            'campaign' => $payload['campaign'] ?? null,
            'term' => $payload['term'] ?? null,
            'content' => $payload['content'] ?? null,
            'metadata' => [
                'cart_identifier' => $attribution->cart_identifier,
                'cart_instance' => $attribution->cart_instance,
                'utm' => [
                    'source' => $payload['source'] ?? null,
                    'medium' => $payload['medium'] ?? null,
                    'campaign' => $payload['campaign'] ?? null,
                    'term' => $payload['term'] ?? null,
                    'content' => $payload['content'] ?? null,
                ],
            ],
            'touched_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildAttributionPayload(Affiliate $affiliate, ?Cart $cart, array $context): array
    {
        $expiresAt = null;
        $ttl = (int) config('affiliates.tracking.attribution_ttl_days', 30);

        if ($ttl > 0) {
            $expiresAt = now()->addDays($ttl);
        }

        $cartIdentifier = $cart?->getIdentifier() ?? ($context['cart_identifier'] ?? null);
        $cartInstance = $cart?->instance() ?? ($context['cart_instance'] ?? null);

        if (! $cartInstance && $cart) {
            $cartInstance = 'default';
        }

        return [
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'cart_identifier' => $cartIdentifier,
            'cart_instance' => $cartInstance,
            'cookie_value' => $context['cookie_value'] ?? null,
            'voucher_code' => $context['voucher_code'] ?? Arr::get($context, 'metadata.voucher_code'),
            'source' => $context['source'] ?? $context['utm_source'] ?? null,
            'medium' => $context['medium'] ?? $context['utm_medium'] ?? null,
            'campaign' => $context['campaign'] ?? $context['utm_campaign'] ?? null,
            'term' => $context['term'] ?? $context['utm_term'] ?? null,
            'content' => $context['content'] ?? $context['utm_content'] ?? null,
            'landing_url' => $context['landing_url'] ?? null,
            'referrer_url' => $context['referrer_url'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_id' => $context['user_id'] ?? $this->resolveUserId(),
            'metadata' => $this->mergeMetadata($context),
            'owner_type' => $affiliate->owner_type,
            'owner_id' => $affiliate->owner_id,
            'expires_at' => $expiresAt,
        ];
    }

    private function persistCartMetadata(Cart $cart, Affiliate $affiliate, AffiliateAttribution $attribution): void
    {
        $cart->setMetadata($this->metadataKey(), [
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'attribution_id' => $attribution->getKey(),
            'cookie_value' => $attribution->cookie_value,
            'voucher_code' => $attribution->voucher_code,
            'source' => $attribution->source,
            'campaign' => $attribution->campaign,
            'attached_at' => now()->toIso8601String(),
        ]);
    }

    private function storeCookieAttribution(Affiliate $affiliate, array $context, ?string $cookieValue): AffiliateAttribution
    {
        $payload = $this->buildAttributionPayload($affiliate, null, $context);

        if (! $cookieValue) {
            $cookieValue = (string) Str::uuid();
        }

        $payload['cookie_value'] = $cookieValue;

        $attribution = $this->findAttributionByCookie($cookieValue);

        if ($attribution) {
            $this->fillAttribution($attribution, $payload);
        } else {
            if (! isset($payload['cart_instance']) || $payload['cart_instance'] === null) {
                $payload['cart_instance'] = 'default';
            }

            $attribution = new AffiliateAttribution($payload);
            $attribution->first_seen_at = now();
        }

        $attribution->last_cookie_seen_at = now();
        $attribution->expires_at = $payload['expires_at'];
        $attribution->save();

        return $attribution;
    }

    private function findAttributionByCookie(?string $cookieValue): ?AffiliateAttribution
    {
        if (! $cookieValue) {
            return null;
        }

        $query = AffiliateAttribution::query()
            ->with('affiliate')
            ->where('cookie_value', $cookieValue)
            ->active()
            ->latest('last_cookie_seen_at');

        $this->applyOwnerScope($query);

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fillAttribution(AffiliateAttribution $attribution, array $payload): void
    {
        $nullableKeys = ['expires_at'];

        foreach ($payload as $key => $value) {
            if ($value === null && ! in_array($key, $nullableKeys, true)) {
                unset($payload[$key]);
            }
        }

        if ($payload !== []) {
            $attribution->fill($payload);
        }
    }

    private function readCartMetadata(Cart $cart): ?array
    {
        $metadata = $cart->getMetadata($this->metadataKey());

        return is_array($metadata) ? $metadata : null;
    }

    private function resolveAffiliateFromMetadata(array $metadata): ?Affiliate
    {
        if (isset($metadata['affiliate_id'])) {
            $affiliate = $this->query()->find($metadata['affiliate_id']);

            if ($affiliate instanceof Affiliate) {
                return $affiliate;
            }
        }

        if (isset($metadata['affiliate_code'])) {
            return $this->findByCode((string) $metadata['affiliate_code']);
        }

        return null;
    }

    private function resolveAttributionFromMetadata(array $metadata): ?AffiliateAttribution
    {
        if (! isset($metadata['attribution_id'])) {
            return null;
        }

        return AffiliateAttribution::query()->find($metadata['attribution_id']);
    }

    private function resolveMinorAmount(mixed $value, callable $fallback): ?int
    {
        if ($value === null) {
            $resolved = $fallback();

            if ($resolved === null) {
                return null;
            }

            return (int) ($resolved instanceof Stringable ? (string) $resolved : $resolved);
        }

        if ($value instanceof Stringable) {
            return (int) (string) $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function metadataKey(): string
    {
        return (string) config('affiliates.cart.metadata_key', 'affiliate');
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(mb_trim($code));
    }

    private function shouldDispatch(string $flag): bool
    {
        return (bool) config("affiliates.events.{$flag}", true) && $this->events !== null;
    }

    private function isRateLimited(Affiliate $affiliate, array $context): bool
    {
        $config = config('affiliates.tracking.ip_rate_limit', []);

        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        $ip = $context['ip_address'] ?? (app()->bound('request') ? request()->ip() : null);

        if (! $ip) {
            return false;
        }

        $max = (int) ($config['max'] ?? 0);
        $decay = (int) ($config['decay_minutes'] ?? 1);

        if ($max <= 0) {
            return false;
        }

        /** @var CacheRepository $cache */
        $cache = Cache::store();
        $key = sprintf('affiliates:ip-rate:%s:%s', $affiliate->code, $ip);

        $hits = (int) $cache->increment($key);

        if ($hits === 1) {
            $cache->put($key, $hits, now()->addMinutes($decay));
        }

        return $hits > $max;
    }

    private function isSelfReferral(Affiliate $affiliate): bool
    {
        if (! config('affiliates.tracking.block_self_referral', false)) {
            return false;
        }

        $owner = $this->resolveOwner();

        if (! $owner || ! $affiliate->owner_id || ! $affiliate->owner_type) {
            return false;
        }

        return $owner->getMorphClass() === $affiliate->owner_type
            && $owner->getKey() === $affiliate->owner_id;
    }

    private function isFingerprintBlocked(Affiliate $affiliate, array $context): bool
    {
        $fingerprintConfig = config('affiliates.tracking.fingerprint', []);

        if (! ($fingerprintConfig['enabled'] ?? false)) {
            return false;
        }

        $fingerprint = $this->resolveFingerprint($context);

        if (! $fingerprint) {
            return false;
        }

        if (! ($fingerprintConfig['block_duplicates'] ?? false)) {
            return false;
        }

        return AffiliateAttribution::query()
            ->where('affiliate_id', $affiliate->getKey())
            ->where('metadata->fingerprint', $fingerprint)
            ->active()
            ->exists();
    }

    private function resolveUserId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $user = request()->user();

        if ($user && method_exists($user, 'getAuthIdentifier')) {
            $id = $user->getAuthIdentifier();

            return $id !== null ? (string) $id : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveFingerprint(array $context): ?string
    {
        $fingerprintConfig = config('affiliates.tracking.fingerprint', []);

        if (! ($fingerprintConfig['enabled'] ?? false)) {
            return null;
        }

        $ua = $context['user_agent'] ?? (app()->bound('request') ? request()->userAgent() : null);
        $ip = $context['ip_address'] ?? (app()->bound('request') ? request()->ip() : null);

        if (! $ua && ! $ip) {
            return null;
        }

        return hash('sha256', ($ua ?? '') . '|' . ($ip ?? ''));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function mergeMetadata(array $context): array
    {
        $metadata = $context['metadata'] ?? Arr::only($context, ['coupon', 'notes', 'utm']);
        $fingerprint = $this->resolveFingerprint($context);

        if ($fingerprint) {
            $metadata['fingerprint'] = $fingerprint;
        }

        return $metadata;
    }

    /**
     * @param  array<int, AffiliateConversionData>  $baseConversions
     */
    private function applyMultiLevelCommissions(array $baseConversions, bool $autoApprove, ConversionStatus $statusEnum, ?string $attributionId): void
    {
        $config = config('affiliates.payouts.multi_level', []);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $levels = $config['levels'] ?? [];

        if ($levels === [] || ! is_array($levels)) {
            return;
        }

        foreach ($baseConversions as $conversionData) {
            $affiliate = $this->query()->find($conversionData->affiliateId);

            if (! $affiliate) {
                continue;
            }

            $current = $affiliate->parent;
            $depth = 0;

            foreach ($levels as $share) {
                $depth++;

                if (! $current) {
                    break;
                }

                $portion = (int) round($conversionData->commissionMinor * (float) $share);

                if ($portion > 0) {
                    $model = AffiliateConversion::create([
                        'affiliate_id' => $current->getKey(),
                        'affiliate_code' => $current->code,
                        'affiliate_attribution_id' => $attributionId,
                        'cart_identifier' => $conversionData->cartIdentifier,
                        'cart_instance' => $conversionData->cartInstance,
                        'voucher_code' => $conversionData->voucherCode,
                        'order_reference' => $conversionData->orderReference,
                        'subtotal_minor' => 0,
                        'total_minor' => 0,
                        'commission_minor' => $portion,
                        'commission_currency' => $conversionData->commissionCurrency,
                        'status' => $autoApprove ? ConversionStatus::Approved : $statusEnum,
                        'channel' => 'upline',
                        'metadata' => [
                            'upline_of' => $affiliate->getKey(),
                            'level' => $depth,
                            'weight' => $share,
                            'base_conversion' => $conversionData->id,
                        ],
                        'owner_type' => $current->owner_type,
                        'owner_id' => $current->owner_id,
                        'occurred_at' => now(),
                        'approved_at' => $autoApprove ? now() : null,
                    ]);

                    $uplineData = AffiliateConversionData::fromModel($model);

                    if ($this->shouldDispatch('dispatch_conversion')) {
                        $this->events?->dispatch(new AffiliateConversionRecorded($uplineData));
                    }

                    if ($this->shouldDispatch('dispatch_webhooks')) {
                        $this->webhooks->dispatch('conversion', $uplineData->toArray());
                    }
                }

                $current = $current->parent;
            }
        }
    }

    private function applyOwnerScope(Builder $query): Builder
    {
        $owner = $this->resolveOwner();

        if ($owner) {
            $query
                ->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        }

        return $query;
    }

    private function pruneAttributionOverflow(
        ?string $cartIdentifier,
        ?string $cartInstance,
        ?string $ownerType,
        ?string $ownerId
    ): void {
        $max = (int) config('affiliates.tracking.max_attributions_per_identifier', 0);

        if ($max <= 0 || ! $cartIdentifier) {
            return;
        }

        $query = AffiliateAttribution::query()
            ->where('cart_identifier', $cartIdentifier)
            ->when($cartInstance, static fn (Builder $builder, string $instance): Builder => $builder->where(
                'cart_instance',
                $instance
            ))
            ->orderByDesc('last_seen_at');

        if (config('affiliates.owner.enabled', false) && $ownerType && $ownerId) {
            $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        }

        $ids = $query->pluck('id');

        if ($ids->count() <= $max) {
            return;
        }

        $toDelete = $ids->slice($max)->all();

        if ($toDelete !== []) {
            AffiliateAttribution::query()
                ->whereIn('id', $toDelete)
                ->delete();
        }
    }

    private function resolveOwner(): ?Model
    {
        if (! config('affiliates.owner.enabled', false)) {
            return null;
        }

        return $this->ownerResolver->resolve();
    }
}
