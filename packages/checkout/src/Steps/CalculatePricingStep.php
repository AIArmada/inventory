<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use Illuminate\Database\Eloquent\Model;

final class CalculatePricingStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'calculate_pricing';
    }

    public function getName(): string
    {
        return 'Calculate Pricing';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['validate_cart', 'resolve_customer'];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        if (empty($items)) {
            return $this->failed('No items in cart snapshot');
        }

        $pricingData = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $itemPrice = $item['price'] ?? 0;
            $quantity = $item['quantity'] ?? 1;
            $lineTotal = $itemPrice * $quantity;

            $pricingData['items'][] = [
                'item_id' => $item['id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'unit_price' => $itemPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        // Apply any pricing rules if pricing package is available
        if ($this->hasPricingPackage()) {
            $pricingData = $this->applyPricingRules($session, $pricingData, $subtotal);
            $subtotal = $pricingData['subtotal'] ?? $subtotal;
        }

        $pricingData['subtotal'] = $subtotal;
        $pricingData['calculated_at'] = now()->toIso8601String();

        $session->update([
            'pricing_data' => $pricingData,
            'subtotal' => $subtotal,
        ]);

        $session->calculateTotals();
        $session->save();

        return $this->success('Pricing calculated', [
            'subtotal' => $subtotal,
            'item_count' => count($items),
        ]);
    }

    private function hasPricingPackage(): bool
    {
        return class_exists(\AIArmada\Pricing\PricingServiceProvider::class);
    }

    /**
     * @param  array<string, mixed>  $pricingData
     * @return array<string, mixed>
     */
    private function applyPricingRules(CheckoutSession $session, array $pricingData, int $subtotal): array
    {
        if (! class_exists(PriceCalculatorInterface::class)) {
            $pricingData['subtotal'] = $subtotal;

            return $pricingData;
        }

        $calculator = app(PriceCalculatorInterface::class);

        $cartSnapshot = $session->cart_snapshot ?? [];
        $items = $cartSnapshot['items'] ?? [];

        $updatedItems = [];
        $newSubtotal = 0;

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPrice = (int) ($item['price'] ?? 0);

            $originalUnitPrice = $unitPrice;
            $discountAmount = 0;
            $discountSource = null;
            $priceListName = null;
            $tierDescription = null;
            $promotionName = null;
            $pricingBreakdown = [];

            $priceable = $this->resolvePriceable($item);
            if ($priceable !== null) {
                $result = $calculator->calculate($priceable, $quantity, [
                    'customer_id' => $session->customer_id,
                    'currency' => $session->currency,
                ]);

                $unitPrice = $result->finalPrice;
                $originalUnitPrice = $result->originalPrice;
                $discountAmount = $result->discountAmount;
                $discountSource = $result->discountSource;
                $priceListName = $result->priceListName;
                $tierDescription = $result->tierDescription;
                $promotionName = $result->promotionName;
                $pricingBreakdown = $result->breakdown;
            }

            $lineTotal = $unitPrice * max(1, $quantity);
            $newSubtotal += $lineTotal;

            $updatedItems[] = [
                'item_id' => $item['id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'original_unit_price' => $originalUnitPrice,
                'discount_amount' => $discountAmount,
                'discount_source' => $discountSource,
                'price_list' => $priceListName,
                'tier' => $tierDescription,
                'promotion' => $promotionName,
                'pricing_breakdown' => $pricingBreakdown,
            ];
        }

        $pricingData['items'] = $updatedItems;
        $pricingData['subtotal'] = $newSubtotal;

        return $pricingData;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolvePriceable(array $item): ?Priceable
    {
        $associated = $item['associated_model'] ?? null;

        if ($associated instanceof Priceable) {
            return $associated;
        }

        if (! is_array($associated)) {
            return null;
        }

        $class = $associated['class'] ?? null;
        $id = $associated['id'] ?? null;

        if (! is_string($class) || $class === '' || $id === null || ! class_exists($class)) {
            return null;
        }

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model|null $model */
        $model = $class::query()->find((string) $id);

        return $model instanceof Priceable ? $model : null;
    }
}
