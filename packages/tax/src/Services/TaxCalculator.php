<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services;

use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Settings\TaxSettings;
use AIArmada\Tax\Settings\TaxZoneSettings;
use AIArmada\Tax\Support\TaxOwnerScope;
use Throwable;

class TaxCalculator
{
    /**
     * Calculate tax for an amount.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculateTax(
        int $amountInCents,
        string $taxClass = 'standard',
        ?string $zoneId = null,
        array $context = []
    ): TaxResultData {
        if (! $this->isTaxEnabled()) {
            return $this->createZeroResult($zoneId);
        }

        // Add zone ID to context for exemption checking
        $context['zone_id'] = $zoneId;

        // Check for exemption first
        $exemption = $this->checkExemption($context);
        if ($exemption) {
            return $this->createExemptResult($exemption);
        }

        // Resolve zone
        $zone = $this->resolveZone($zoneId, $context);

        // Get applicable rate
        $rate = $this->getRate($taxClass, $zone);

        // Calculate tax
        $pricesIncludeTax = $this->getPricesIncludeTax();
        $taxAmount = $pricesIncludeTax
            ? $rate->extractTax($amountInCents)
            : $rate->calculateTax($amountInCents);

        // Round if configured
        if (config('tax.defaults.round_at_subtotal', true)) {
            $taxAmount = (int) round($taxAmount);
        }

        return new TaxResultData(
            taxAmount: $taxAmount,
            rate: $rate,
            zone: $zone,
            includedInPrice: $pricesIncludeTax,
        );
    }

    /**
     * Calculate tax for shipping.
     */
    public function calculateShippingTax(int $shippingAmountInCents, ?string $zoneId = null, array $context = []): TaxResultData
    {
        if (! $this->isShippingTaxable()) {
            return $this->createZeroResult($zoneId);
        }

        // Use standard tax class for shipping
        return $this->calculateTax($shippingAmountInCents, 'standard', $zoneId, $context);
    }

    /**
     * Resolve the tax zone.
     *
     * @param  array<string, mixed>  $context
     */
    protected function resolveZone(?string $zoneId, array $context): TaxZone
    {
        // If zone ID provided, use it
        if ($zoneId) {
            $zone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($zoneId)
                ->first();
            if ($zone) {
                return $zone;
            }
        }

        // Try to resolve from address in context
        if ($this->useCustomerAddressForZoneResolution()) {
            $addressPriority = $this->getAddressPriority();
            $address = $context["{$addressPriority}_address"] ?? $context['address'] ?? null;

            if ($address) {
                $zone = $this->findZoneByAddress(
                    $address['country'] ?? 'MY',
                    $address['state'] ?? null,
                    $address['postcode'] ?? null
                );

                if ($zone) {
                    return $zone;
                }
            }
        }

        // Use default zone
        $defaultZone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
            ->default()
            ->active()
            ->first();
        if ($defaultZone) {
            return $defaultZone;
        }

        $fallbackZoneId = $this->getFallbackZoneId();
        if ($fallbackZoneId) {
            $fallbackZone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($fallbackZoneId)
                ->first();
            if ($fallbackZone) {
                return $fallbackZone;
            }
        }

        // Handle unknown zone based on config
        return match ($this->getUnknownZoneBehavior()) {
            'zero' => TaxZone::zeroRate(),
            'error' => throw new TaxZoneNotFoundException('No tax zone could be resolved'),
            default => TaxZone::zeroRate(),
        };
    }

    /**
     * Find zone by address.
     */
    protected function findZoneByAddress(string $country, ?string $state, ?string $postcode): ?TaxZone
    {
        return TaxOwnerScope::applyToOwnedQuery(TaxZone::forAddress($country, $state, $postcode))
            ->get()
            ->first(fn (TaxZone $zone) => $zone->matchesAddress($country, $state, $postcode));
    }

    /**
     * Get the tax rate for a class and zone.
     */
    protected function getRate(string $taxClass, TaxZone $zone): TaxRate
    {
        $rate = TaxOwnerScope::applyToOwnedQuery(TaxRate::query())
            ->where('zone_id', $zone->id)
            ->where('tax_class', $taxClass)
            ->active()
            ->orderBy('priority', 'desc')
            ->first();

        return $rate ?? TaxRate::zeroRate($taxClass, $zone);
    }

    /**
     * Check for tax exemption.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkExemption(array $context): ?TaxExemption
    {
        if (! config('tax.features.exemptions.enabled', true)) {
            return null;
        }

        $customerId = $context['customer_id'] ?? null;
        if (! $customerId) {
            return null;
        }

        $zoneId = $context['zone_id'] ?? null;

        $query = TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())
            ->where('exemptable_id', $customerId)
            ->active()
            ->forZone($zoneId);

        return $query->first();
    }

    /**
     * Create an exempt result.
     */
    protected function createExemptResult(TaxExemption $exemption): TaxResultData
    {
        $zone = TaxZone::zeroRate();
        $rate = TaxRate::zeroRate('exempt', $zone);

        return new TaxResultData(
            taxAmount: 0,
            rate: $rate,
            zone: $zone,
            includedInPrice: false,
            exemptionReason: $exemption->reason,
        );
    }

    /**
     * Create a zero-tax result.
     */
    protected function createZeroResult(?string $zoneId): TaxResultData
    {
        $zone = null;

        if ($zoneId !== null) {
            $zone = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())
                ->whereKey($zoneId)
                ->first();
        }

        $zone ??= TaxZone::zeroRate();
        $rate = TaxRate::zeroRate('zero', $zone);

        return new TaxResultData(
            taxAmount: 0,
            rate: $rate,
            zone: $zone,
            includedInPrice: false,
        );
    }

    private function isTaxEnabled(): bool
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->enabled;
        }

        return (bool) config('tax.features.enabled', true);
    }

    private function getPricesIncludeTax(): bool
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->pricesIncludeTax;
        }

        return (bool) config('tax.defaults.prices_include_tax', false);
    }

    private function isShippingTaxable(): bool
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->shippingTaxable;
        }

        return (bool) config('tax.defaults.calculate_tax_on_shipping', true);
    }

    private function useCustomerAddressForZoneResolution(): bool
    {
        $settings = $this->getTaxZoneSettings();
        if ($settings) {
            return $settings->autoDetectZone;
        }

        return (bool) config('tax.features.zone_resolution.use_customer_address', true);
    }

    private function getAddressPriority(): string
    {
        $settings = $this->getTaxSettings();
        if ($settings) {
            return $settings->taxBasedOnShippingAddress ? 'shipping' : 'billing';
        }

        return (string) config('tax.features.zone_resolution.address_priority', 'shipping');
    }

    private function getUnknownZoneBehavior(): string
    {
        $settings = $this->getTaxZoneSettings();
        if ($settings) {
            return $settings->fallbackBehavior;
        }

        return (string) config('tax.features.zone_resolution.unknown_zone_behavior', 'default');
    }

    private function getFallbackZoneId(): ?string
    {
        $settings = $this->getTaxZoneSettings();
        if ($settings) {
            return $settings->defaultZoneId;
        }

        /** @var string|null $fallbackZoneId */
        $fallbackZoneId = config('tax.features.zone_resolution.fallback_zone_id');

        return $fallbackZoneId;
    }

    private function getTaxSettings(): ?TaxSettings
    {
        try {
            /** @var TaxSettings $settings */
            $settings = app(TaxSettings::class);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }

    private function getTaxZoneSettings(): ?TaxZoneSettings
    {
        try {
            /** @var TaxZoneSettings $settings */
            $settings = app(TaxZoneSettings::class);

            return $settings;
        } catch (Throwable) {
            return null;
        }
    }
}
