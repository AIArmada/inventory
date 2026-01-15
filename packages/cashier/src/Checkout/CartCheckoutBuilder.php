<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Checkout;

use AIArmada\Cart\Cart;
use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\CheckoutBuilderContract;
use AIArmada\Cashier\Contracts\CheckoutContract;
use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/**
 * Checkout builder that orchestrates cart → payment flow.
 *
 * Provides a fluent API for creating checkout sessions from carts:
 * - Converts cart items to payment line items
 * - Handles inventory allocation before payment
 * - Preserves cart metadata for post-payment processing
 * - Supports multiple payment gateways
 */
final class CartCheckoutBuilder
{
    private Cart $cart;

    private GatewayContract $gateway;

    private ?BillableContract $customer = null;

    private ?string $successUrl = null;

    private ?string $cancelUrl = null;

    private string $mode = 'payment';

    private bool $allocateInventory = true;

    private int $inventoryTtl = 30;

    private bool $validateStock = true;

    /** @var array<string, mixed> */
    private array $metadata = [];

    /** @var array<string, mixed> */
    private array $gatewayOptions = [];

    public function __construct(Cart $cart, GatewayContract $gateway)
    {
        $this->cart = $cart;
        $this->gateway = $gateway;
        $this->allocateInventory = config('cashier.cart.allocate_inventory', true);
        $this->inventoryTtl = config('cashier.cart.inventory_ttl_minutes', 30);
        $this->validateStock = config('cashier.cart.validate_stock', true);
    }

    /**
     * Set the customer for the checkout.
     */
    public function customer(BillableContract $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    /**
     * Set the success redirect URL.
     */
    public function successUrl(string $url): self
    {
        $this->successUrl = $url;

        return $this;
    }

    /**
     * Set the cancel redirect URL.
     */
    public function cancelUrl(string $url): self
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Set the checkout mode.
     */
    public function mode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Configure inventory allocation before payment.
     */
    public function allocateInventory(bool $allocate = true, int $ttlMinutes = 30): self
    {
        $this->allocateInventory = $allocate;
        $this->inventoryTtl = $ttlMinutes;

        return $this;
    }

    /**
     * Disable inventory allocation.
     */
    public function withoutInventoryAllocation(): self
    {
        $this->allocateInventory = false;

        return $this;
    }

    /**
     * Configure stock validation.
     */
    public function validateStock(bool $validate = true): self
    {
        $this->validateStock = $validate;

        return $this;
    }

    /**
     * Add metadata to the checkout.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Add gateway-specific options.
     *
     * @param  array<string, mixed>  $options
     */
    public function gatewayOptions(array $options): self
    {
        $this->gatewayOptions = array_merge($this->gatewayOptions, $options);

        return $this;
    }

    /**
     * Process the checkout: validate, allocate inventory, create payment.
     *
     * @throws \AIArmada\Cashier\Exceptions\CheckoutException
     */
    public function process(): CheckoutContract
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            // Validate stock if configured
            if ($this->validateStock) {
                $this->performStockValidation();
            }

            // Allocate inventory if configured
            if ($this->allocateInventory) {
                $this->performInventoryAllocation();
            }

            // Build and create the checkout session
            return $this->createCheckoutSession();
        });
    }

    /**
     * Process and redirect to the payment page.
     */
    public function redirect(): RedirectResponse
    {
        $checkout = $this->process();

        return redirect($checkout->url());
    }

    /**
     * Validate stock availability for all cart items.
     *
     * @throws \AIArmada\Cashier\Exceptions\InsufficientStockException
     */
    private function performStockValidation(): void
    {
        if (! class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            return;
        }

        // Use cart's inventory validation if available
        $cartManager = app('cart');

        if (method_exists($cartManager, 'validateInventory')) {
            $result = $cartManager->validateInventory($this->cart->getId());

            if (! $result['valid']) {
                throw new \AIArmada\Cashier\Exceptions\InsufficientStockException(
                    'Insufficient stock for some items',
                    $result['insufficient_items'] ?? []
                );
            }
        }
    }

    /**
     * Allocate inventory for cart items.
     */
    private function performInventoryAllocation(): void
    {
        if (! class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            return;
        }

        $cartManager = app('cart');

        if (method_exists($cartManager, 'allocateAllInventory')) {
            $cartManager->allocateAllInventory($this->cart->getId(), $this->inventoryTtl);
        }
    }

    /**
     * Create the checkout session with the gateway.
     */
    private function createCheckoutSession(): CheckoutContract
    {
        if (! $this->customer) {
            throw new InvalidArgumentException('Customer is required for checkout. Call customer() first.');
        }

        // Build checkout with gateway
        $checkoutBuilder = $this->gateway->checkout($this->customer);

        // Add cart items as line items
        foreach ($this->cart->getItems() as $item) {
            $this->addItemToCheckout($checkoutBuilder, $item);
        }

        // Set URLs
        if ($this->successUrl) {
            $checkoutBuilder->successUrl($this->successUrl);
        }

        if ($this->cancelUrl) {
            $checkoutBuilder->cancelUrl($this->cancelUrl);
        }

        // Set mode
        $checkoutBuilder->mode($this->mode);

        // Build metadata including cart reference
        $metadata = array_merge($this->metadata, [
            config('cashier.cart.metadata_key', 'cart_id') => $this->cart->getId(),
            'cart_total' => $this->cart->total(),
            'cart_items_count' => $this->cart->count(),
        ]);

        // Include affiliate data if present
        $affiliateData = $this->cart->getMetadata('affiliate');
        if ($affiliateData) {
            $metadata['affiliate_id'] = $affiliateData['affiliate_id'] ?? null;
            $metadata['affiliate_code'] = $affiliateData['affiliate_code'] ?? null;
        }

        $checkoutBuilder->metadata($metadata);

        return $checkoutBuilder->create();
    }

    /**
     * Add a cart item to the checkout builder.
     */
    private function addItemToCheckout(CheckoutBuilderContract $builder, LineItemInterface $item): void
    {
        // Gateway-specific implementation
        // For gateways that support dynamic pricing (like CHIP)
        // we pass the item details directly

        // For gateways requiring pre-configured prices (like Stripe)
        // we look for a price_id in the item attributes

        $metadata = $item->getLineItemMetadata();
        $priceId = $metadata['price_id'] ?? null;
        $quantity = (int) $item->getLineItemQuantity();

        if (is_string($priceId) && $priceId !== '') {
            $builder->price($priceId, $quantity);
        } else {
            // For dynamic pricing, we'd need gateway-specific handling
            // This could be extended via gateway adapters
            $builder->price($item->getLineItemId(), $quantity);
        }
    }
}
