<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud\Detectors;

use AIArmada\Cart\Security\Fraud\DetectorResult;
use AIArmada\Cart\Security\Fraud\FraudContext;
use AIArmada\Cart\Security\Fraud\FraudDetectorInterface;
use AIArmada\Cart\Security\Fraud\FraudSignal;
use Illuminate\Support\Facades\Cache;

/**
 * Detects potential price manipulation attacks.
 *
 * Monitors for patterns like:
 * - Items with prices that don't match catalog
 * - Negative prices or quantities
 * - Unusually low totals for high-value items
 * - Price changes after items added to cart
 */
final class PriceManipulationDetector implements FraudDetectorInterface
{
    private const NAME = 'price_manipulation';

    private const CACHE_PREFIX = 'fraud:prices:';

    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    public function __construct()
    {
        $this->configuration = config('cart.fraud.detectors.price_manipulation', [
            'enabled' => true,
            'weight' => 1.5,
            'max_discount_percentage' => 50,
            'min_valid_price' => 1,
            'max_quantity_per_item' => 100,
            'price_variance_threshold' => 0.01,
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function isEnabled(): bool
    {
        return $this->configuration['enabled'] ?? true;
    }

    public function getWeight(): float
    {
        return $this->configuration['weight'] ?? 1.5;
    }

    public function detect(FraudContext $context): DetectorResult
    {
        $startTime = microtime(true);
        $signals = [];

        $signals = array_merge($signals, $this->checkNegativeValues($context));
        $signals = array_merge($signals, $this->checkSuspiciouslyLowPrices($context));
        $signals = array_merge($signals, $this->checkExcessiveQuantities($context));
        $signals = array_merge($signals, $this->checkPriceVariance($context));
        $signals = array_merge($signals, $this->checkTotalMismatch($context));

        $executionTime = (int) ((microtime(true) - $startTime) * 1000);

        return DetectorResult::withSignals(
            self::NAME,
            $signals,
            $executionTime,
            ['items_checked' => $context->getItemCount()]
        );
    }

    /**
     * Store catalog price for comparison.
     */
    public function storeCatalogPrice(string $itemId, int $price): void
    {
        $key = "catalog:price:{$itemId}";
        Cache::put($key, $price, 86400);
    }

    /**
     * Check for negative prices or quantities.
     *
     * @return array<FraudSignal>
     */
    private function checkNegativeValues(FraudContext $context): array
    {
        $signals = [];
        $items = $context->cart->getItems();

        foreach ($items as $item) {
            if ($item->price < 0) {
                $signals[] = FraudSignal::high(
                    'negative_price',
                    self::NAME,
                    "Item '{$item->name}' has negative price: {$item->price}",
                    'Block transaction immediately - clear sign of manipulation',
                    ['item_id' => $item->id, 'price' => $item->price]
                );
            }

            if ($item->quantity < 0) {
                $signals[] = FraudSignal::high(
                    'negative_quantity',
                    self::NAME,
                    "Item '{$item->name}' has negative quantity: {$item->quantity}",
                    'Block transaction immediately - clear sign of manipulation',
                    ['item_id' => $item->id, 'quantity' => $item->quantity]
                );
            }
        }

        return $signals;
    }

    /**
     * Check for suspiciously low prices.
     *
     * @return array<FraudSignal>
     */
    private function checkSuspiciouslyLowPrices(FraudContext $context): array
    {
        $signals = [];
        $items = $context->cart->getItems();
        $minValidPrice = $this->configuration['min_valid_price'] ?? 1;

        foreach ($items as $item) {
            if ($item->price > 0 && $item->price < $minValidPrice) {
                $signals[] = FraudSignal::medium(
                    'suspiciously_low_price',
                    self::NAME,
                    "Item '{$item->name}' has unusually low price: {$item->price} cents",
                    'Verify price against catalog before processing',
                    ['item_id' => $item->id, 'price' => $item->price]
                );
            }

            $catalogPrice = $this->getCatalogPrice($item->id);
            if ($catalogPrice !== null) {
                $discountPercentage = (($catalogPrice - $item->price) / $catalogPrice) * 100;
                $maxDiscount = $this->configuration['max_discount_percentage'] ?? 50;

                if ($discountPercentage > $maxDiscount) {
                    $signals[] = FraudSignal::high(
                        'excessive_discount',
                        self::NAME,
                        "Item '{$item->name}' has {$discountPercentage}% discount (catalog: {$catalogPrice}, cart: {$item->price})",
                        'Verify discount is legitimate and authorized',
                        [
                            'item_id' => $item->id,
                            'catalog_price' => $catalogPrice,
                            'cart_price' => $item->price,
                            'discount_percentage' => $discountPercentage,
                        ]
                    );
                }
            }
        }

        return $signals;
    }

    /**
     * Check for excessive quantities.
     *
     * @return array<FraudSignal>
     */
    private function checkExcessiveQuantities(FraudContext $context): array
    {
        $signals = [];
        $items = $context->cart->getItems();
        $maxQuantity = $this->configuration['max_quantity_per_item'] ?? 100;

        foreach ($items as $item) {
            if ($item->quantity > $maxQuantity) {
                $signals[] = FraudSignal::medium(
                    'excessive_quantity',
                    self::NAME,
                    "Item '{$item->name}' has unusual quantity: {$item->quantity}",
                    'Verify this is a legitimate bulk order',
                    ['item_id' => $item->id, 'quantity' => $item->quantity, 'max_allowed' => $maxQuantity]
                );
            }
        }

        $totalQuantity = $context->getTotalQuantity();
        if ($totalQuantity > ($maxQuantity * 5)) {
            $signals[] = FraudSignal::medium(
                'excessive_total_quantity',
                self::NAME,
                "Cart has unusually high total quantity: {$totalQuantity} items",
                'Review order for potential abuse or reselling',
                ['total_quantity' => $totalQuantity]
            );
        }

        return $signals;
    }

    /**
     * Check for price variance compared to original.
     *
     * @return array<FraudSignal>
     */
    private function checkPriceVariance(FraudContext $context): array
    {
        $signals = [];
        $items = $context->cart->getItems();
        $threshold = $this->configuration['price_variance_threshold'] ?? 0.01;

        foreach ($items as $item) {
            $originalPrice = $this->getOriginalCartPrice($context->getCartId(), $item->id);

            if ($originalPrice !== null && $originalPrice > 0) {
                $variance = abs($item->price - $originalPrice) / $originalPrice;

                if ($variance > $threshold && $item->price < $originalPrice) {
                    $signals[] = FraudSignal::high(
                        'price_variance',
                        self::NAME,
                        "Item '{$item->name}' price changed from {$originalPrice} to {$item->price} after being added to cart",
                        'Price may have been manipulated via API or form tampering',
                        [
                            'item_id' => $item->id,
                            'original_price' => $originalPrice,
                            'current_price' => $item->price,
                            'variance' => $variance,
                        ]
                    );
                }
            }

            $this->storeOriginalCartPrice($context->getCartId(), $item->id, $item->price);
        }

        return $signals;
    }

    /**
     * Check for total mismatches.
     *
     * @return array<FraudSignal>
     */
    private function checkTotalMismatch(FraudContext $context): array
    {
        $signals = [];
        $items = $context->cart->getItems();

        $calculatedSubtotal = 0;
        foreach ($items as $item) {
            $calculatedSubtotal += $item->price * $item->quantity;
        }

        $cartSubtotal = $context->cart->getRawSubtotal();

        if ($calculatedSubtotal !== $cartSubtotal) {
            $difference = abs($calculatedSubtotal - $cartSubtotal);
            $percentageDiff = ($difference / max($calculatedSubtotal, 1)) * 100;

            if ($percentageDiff > 0.1) {
                $signals[] = FraudSignal::high(
                    'subtotal_mismatch',
                    self::NAME,
                    "Cart subtotal ({$cartSubtotal}) doesn't match calculated sum ({$calculatedSubtotal})",
                    'Possible cart data corruption or manipulation',
                    [
                        'cart_subtotal' => $cartSubtotal,
                        'calculated_subtotal' => $calculatedSubtotal,
                        'difference' => $difference,
                    ]
                );
            }
        }

        return $signals;
    }

    /**
     * Get catalog price for an item (mock implementation).
     */
    private function getCatalogPrice(string $itemId): ?int
    {
        $key = "catalog:price:{$itemId}";

        return Cache::get($key);
    }

    /**
     * Get original cart price when item was added.
     */
    private function getOriginalCartPrice(string $cartId, string $itemId): ?int
    {
        $key = self::CACHE_PREFIX."{$cartId}:{$itemId}";

        return Cache::get($key);
    }

    /**
     * Store original cart price.
     */
    private function storeOriginalCartPrice(string $cartId, string $itemId, int $price): void
    {
        $key = self::CACHE_PREFIX."{$cartId}:{$itemId}";
        Cache::put($key, $price, 86400);
    }
}
