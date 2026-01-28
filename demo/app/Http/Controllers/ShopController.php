<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Facades\Chip;
use AIArmada\Chip\Testing\WebhookSimulator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderAddress;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;
use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Models\Voucher;
use App\Listeners\HandleChipPaymentSuccess;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class ShopController extends Controller
{
    /**
     * Homepage with featured products and categories.
     */
    public function home(): View
    {
        $owner = OwnerContext::resolve();

        $categories = Category::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->withCount('products')
            ->get();

        $featuredProducts = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with('categories')
            ->where('status', ProductStatus::Active)
            ->inRandomOrder()
            ->take(8)
            ->get();

        $activeVouchers = Voucher::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', VoucherStatus::normalize(Active::class))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->take(3)
            ->get();

        return view('shop.home', compact('categories', 'featuredProducts', 'activeVouchers'));
    }

    /**
     * Products listing with filters.
     */
    public function products(Request $request): View
    {
        $owner = OwnerContext::resolve();

        $categories = Category::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->withCount('products')
            ->get();
        $currentCategory = null;

        $query = Product::query()
            ->when(
                $owner,
                fn ($builder) => $builder->forOwner($owner),
                fn ($builder) => $builder->whereRaw('1 = 0'),
            )
            ->with('categories')
            ->where('status', ProductStatus::Active);

        // Category filter
        if ($request->filled('category')) {
            $currentCategory = Category::query()
                ->when(
                    $owner,
                    fn ($builder) => $builder->forOwner($owner),
                    fn ($builder) => $builder->whereRaw('1 = 0'),
                )
                ->where('slug', $request->category)
                ->first();
            if ($currentCategory) {
                $query->whereHas('categories', fn ($q) => $q->whereKey($currentCategory->id));
            }
        }

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Price filter
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (int) $request->min_price * 100);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (int) $request->max_price * 100);
        }

        // In-stock filtering is handled by the Inventory package in admin.

        // Sorting
        $sort = $request->get('sort', 'newest');
        $query = match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name' => $query->orderBy('name', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(12);

        return view('shop.products', compact('products', 'categories', 'currentCategory'));
    }

    /**
     * Categories listing.
     */
    public function categories(): View
    {
        $owner = OwnerContext::resolve();

        $categories = Category::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->withCount('products')
            ->get();

        return view('shop.categories', compact('categories'));
    }

    /**
     * Single product page.
     */
    public function product(Product $product): View
    {
        $this->ensureProductAccessible($product);

        $product->load('categories');

        $primaryCategoryId = $product->categories->first()?->getKey();

        $owner = OwnerContext::resolve();

        $relatedProducts = Product::query()
            ->when(
                $owner,
                fn ($builder) => $builder->forOwner($owner),
                fn ($builder) => $builder->whereRaw('1 = 0'),
            )
            ->with('categories')
            ->where('status', ProductStatus::Active)
            ->where('id', '!=', $product->id)
            ->when($primaryCategoryId, fn ($q) => $q->whereHas('categories', fn ($sub) => $sub->whereKey($primaryCategoryId)))
            ->inRandomOrder()
            ->take(4)
            ->get();

        return view('shop.product', compact('product', 'relatedProducts'));
    }

    /**
     * Shopping cart page.
     */
    public function cart(): View
    {
        $cartItems = Cart::getItems();
        $cartTotal = Cart::isEmpty() ? 0 : Cart::getRawTotal();
        $cartSubtotal = Cart::isEmpty() ? 0 : Cart::getRawSubtotalWithoutConditions();
        $cartQuantity = Cart::getTotalQuantity();

        $appliedVoucher = session('applied_voucher');
        $appliedVouchers = Cart::getAppliedVoucherCodes();
        $cartConditions = Cart::getConditions()->toArray();

        $owner = OwnerContext::resolve();

        $activeVouchers = Voucher::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', VoucherStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->take(3)
            ->get();

        return view('shop.cart', compact('cartItems', 'cartTotal', 'cartSubtotal', 'cartQuantity', 'activeVouchers', 'appliedVoucher', 'appliedVouchers', 'cartConditions'));
    }

    /**
     * Add item to cart.
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => 'required|integer|min:1',
        ]);

        $owner = OwnerContext::resolve();

        $product = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereKey($request->product_id)
            ->firstOrFail();

        if (! $product->isPurchasable()) {
            return back()->with('error', 'Sorry, this product is not available for purchase.');
        }

        $quantity = max(1, (int) $request->quantity);

        /** @var PriceCalculator $priceCalculator */
        $priceCalculator = app(PriceCalculator::class);

        $priceResult = $priceCalculator->calculate($product, $quantity, [
            'currency' => 'MYR',
        ]);

        Cart::add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $priceResult->finalPrice,
            'quantity' => $quantity,
            'attributes' => [
                'sku' => $product->sku,
                'category' => $product->categories->first()?->name,
                'slug' => $product->slug,
            ],
        ]);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return back()->with('success', "{$product->name} added to cart!");
    }

    /**
     * Update cart item quantity.
     */
    public function updateCart(Request $request, string $itemId): RedirectResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $quantity = max(1, (int) $request->quantity);

        $owner = OwnerContext::resolve();

        $product = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereKey($itemId)
            ->first();

        $unitPrice = null;

        if ($product !== null) {
            /** @var PriceCalculator $priceCalculator */
            $priceCalculator = app(PriceCalculator::class);

            $priceResult = $priceCalculator->calculate($product, $quantity, [
                'currency' => 'MYR',
            ]);

            $unitPrice = $priceResult->finalPrice;
        }

        Cart::update($itemId, [
            'quantity' => [
                'relative' => false,
                'value' => $quantity,
            ],
            ...($unitPrice !== null ? ['price' => $unitPrice] : []),
        ]);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return back()->with('success', 'Cart updated.');
    }

    /**
     * Remove item from cart.
     */
    public function removeFromCart(string $itemId): RedirectResponse
    {
        Cart::remove($itemId);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return back()->with('success', 'Item removed from cart.');
    }

    /**
     * Apply voucher to cart.
     */
    public function applyVoucher(Request $request): RedirectResponse
    {
        $request->validate([
            'voucher_code' => 'required|string',
        ]);

        try {
            Cart::applyVoucher($request->voucher_code);
            session(['applied_voucher' => mb_strtoupper($request->voucher_code)]);

            return back()->with('success', 'Voucher ' . mb_strtoupper($request->voucher_code) . ' applied!');
        } catch (InvalidVoucherException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove voucher from cart.
     */
    public function removeVoucher(Request $request): RedirectResponse
    {
        $voucherCode = $request->input('voucher_code');

        if ($voucherCode) {
            Cart::removeVoucher($voucherCode);

            // Also clean up session if it matches (for backwards compatibility/simplicity)
            $appliedInSession = session('applied_voucher');
            if ($appliedInSession === mb_strtoupper((string) $voucherCode)) {
                session()->forget('applied_voucher');
            }

            return back()->with('success', 'Voucher ' . mb_strtoupper((string) $voucherCode) . ' removed.');
        }

        // Fallback for when no code provided (shouldn't happen with new UI)
        $appliedVoucher = session('applied_voucher');

        if ($appliedVoucher) {
            Cart::removeVoucher($appliedVoucher);
        }

        session()->forget('applied_voucher');

        return back()->with('success', 'Voucher removed.');
    }

    /**
     * Checkout page.
     */
    public function checkout(): View | RedirectResponse
    {
        if (Cart::isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        // Mark cart as checkout started for recovery tracking
        $identifier = Cart::getIdentifier();
        $snapshot = CartSnapshot::query()->forOwner()->where('identifier', $identifier)->first();

        if ($snapshot !== null) {
            $snapshot->markCheckoutStarted();
        }

        $items = Cart::getItems();
        $subtotalWithoutConditions = Cart::getRawSubtotalWithoutConditions();
        $subtotal = Cart::getRawSubtotal();
        $total = Cart::getRawTotal();
        $conditions = Cart::getConditions();

        return view('shop.checkout', compact('items', 'subtotalWithoutConditions', 'subtotal', 'total', 'conditions'));
    }

    /**
     * Process checkout and create order, then redirect to CHIP payment.
     */
    public function processCheckout(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'line1' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'postcode' => 'required|string',
            'shipping_method' => 'required|in:jnt_standard,jnt_express,free',
            'payment_method' => 'required|in:fpx,card,ewallet',
        ]);

        if (Cart::isEmpty()) {
            return redirect()->route('shop.cart')->with('error', 'Your cart is empty.');
        }

        // Calculate shipping cost
        $shippingCost = match ($request->shipping_method) {
            'jnt_express' => 1500,
            'free' => 0,
            default => 800,
        };

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        $customer = Customer::query()
            ->forOwner($owner)
            ->where('email', $request->email)
            ->first();

        if ($customer === null) {
            $customer = new Customer([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'user_id' => Auth::id(),
            ]);

            if ($owner !== null) {
                $customer->assignOwner($owner);
            }

            $customer->save();
        } else {
            $customer->fill([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'user_id' => Auth::id(),
            ]);
            $customer->save();
        }

        /** @var PriceCalculator $priceCalculator */
        $priceCalculator = app(PriceCalculator::class);

        /** @var TaxCalculator $taxCalculator */
        $taxCalculator = app(TaxCalculator::class);

        $pricingContext = [
            'currency' => 'MYR',
            'customer_id' => $customer->id,
        ];

        $taxContext = [
            'customer_id' => $customer->id,
            'shipping_address' => [
                'country' => 'MY',
                'state' => $request->state,
                'postcode' => $request->postcode,
            ],
        ];

        $lineSnapshots = [];
        $itemsSubtotal = 0;

        foreach (Cart::getItems() as $item) {
            $product = Product::query()
                ->forOwner($owner)
                ->whereKey((string) $item->id)
                ->firstOrFail();
            $quantity = max(1, (int) $item->quantity);

            $priceResult = $priceCalculator->calculate($product, $quantity, $pricingContext);
            $unitPrice = $priceResult->finalPrice;
            $lineSubtotal = $unitPrice * $quantity;

            $itemsSubtotal += $lineSubtotal;
            $lineSnapshots[] = [
                'product' => $product,
                'name' => $item->name,
                'sku' => $item->attributes['sku'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
            ];

            // Keep cart math consistent with Pricing-derived unit price.
            Cart::update((string) $item->id, ['price' => $unitPrice]);
        }

        // Calculate discount properly (subtotal without conditions - subtotal with conditions)
        $subtotalWithoutConditions = (int) Cart::getRawSubtotalWithoutConditions();
        $subtotalWithConditions = (int) Cart::getRawSubtotal();
        $discountTotal = max(0, $subtotalWithoutConditions - $subtotalWithConditions);

        $taxableItemsAmount = max(0, $itemsSubtotal - $discountTotal);

        $itemsTax = 0;
        $shippingTax = 0;

        try {
            $itemsTax = $taxCalculator
                ->calculateTax($taxableItemsAmount, 'standard', null, $taxContext)
                ->taxAmount;

            $shippingTax = $taxCalculator
                ->calculateShippingTax($shippingCost, null, $taxContext)
                ->taxAmount;
        } catch (QueryException $exception) {
            Log::warning('Tax calculation skipped due to missing DB tables.', [
                'message' => $exception->getMessage(),
            ]);
        }

        $taxTotal = $itemsTax + $shippingTax;

        // Create order with pending_payment status
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'status' => 'pending_payment',
            'customer_type' => $customer->getMorphClass(),
            'customer_id' => $customer->getKey(),
            'subtotal' => $itemsSubtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shippingCost,
            'grand_total' => max(0, $itemsSubtotal - $discountTotal) + $shippingCost + $taxTotal,
            'currency' => 'MYR',
            'metadata' => [
                'shipping_method' => $request->shipping_method,
                'payment_method' => $request->payment_method,
                'affiliate_code' => session('affiliate_code'),
                'voucher_code' => session('applied_voucher'),
                'tax' => [
                    'items_tax' => $itemsTax,
                    'shipping_tax' => $shippingTax,
                ],
            ],
            'notes' => $request->notes,
        ]);

        if ($owner !== null && $order->owner_id === null) {
            $order->assignOwner($owner);
            $order->save();
        }

        OrderAddress::create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'line1' => $request->line1,
            'line2' => $request->line2,
            'city' => $request->city,
            'state' => $request->state,
            'postcode' => $request->postcode,
            'country' => 'MY',
            'phone' => $request->phone,
            'email' => $request->email,
        ]);

        // Create order items (don't deduct stock yet - wait for payment)
        $remainingDiscount = $discountTotal;
        $remainingTax = $itemsTax;

        foreach ($lineSnapshots as $index => $snapshot) {
            $lineSubtotal = (int) $snapshot['line_subtotal'];
            $isLast = $index === array_key_last($lineSnapshots);

            $lineDiscount = 0;
            if ($discountTotal > 0 && $itemsSubtotal > 0) {
                $lineDiscount = $isLast
                    ? $remainingDiscount
                    : (int) floor(($lineSubtotal / $itemsSubtotal) * $discountTotal);
                $lineDiscount = min($remainingDiscount, max(0, $lineDiscount));
                $remainingDiscount -= $lineDiscount;
            }

            $lineTax = 0;
            if ($itemsTax > 0 && $taxableItemsAmount > 0) {
                $taxableLineAmount = max(0, $lineSubtotal - $lineDiscount);
                $lineTax = $isLast
                    ? $remainingTax
                    : (int) floor(($taxableLineAmount / $taxableItemsAmount) * $itemsTax);
                $lineTax = min($remainingTax, max(0, $lineTax));
                $remainingTax -= $lineTax;
            }

            /** @var Product $product */
            $product = $snapshot['product'];
            $quantity = (int) $snapshot['quantity'];
            $unitPrice = (int) $snapshot['unit_price'];

            OrderItem::create([
                'order_id' => $order->id,
                'purchasable_type' => $product->getMorphClass(),
                'purchasable_id' => $product->getKey(),
                'name' => (string) $snapshot['name'],
                'sku' => $snapshot['sku'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineDiscount,
                'tax_amount' => $lineTax,
                'total' => max(0, ($unitPrice * $quantity) - $lineDiscount + $lineTax),
                'currency' => 'MYR',
            ]);
        }

        // Store cart data in session for potential recovery
        session([
            'pending_order_id' => $order->id,
            'pending_affiliate_code' => session('affiliate_code'),
            'pending_voucher_code' => session('applied_voucher'),
        ]);

        $chipApiKey = config('chip.collect.api_key');
        $chipBrandId = config('chip.collect.brand_id');

        if ($chipApiKey === null || $chipBrandId === null) {
            Log::warning('CHIP is not configured; using demo payment simulation.', [
                'order_id' => $order->id,
                'chip_api_key_set' => $chipApiKey !== null,
                'chip_brand_id_set' => $chipBrandId !== null,
            ]);

            $order->update([
                'metadata' => array_merge($order->metadata ?? [], [
                    'chip_purchase_id' => 'demo-' . $order->order_number,
                ]),
            ]);

            return redirect()->route('shop.payment.success', $order);
        }

        // Create CHIP purchase
        try {
            $purchase = Chip::purchase()
                ->currency('MYR')
                ->reference($order->order_number)
                ->customer(
                    email: $request->email,
                    fullName: $request->first_name . ' ' . $request->last_name,
                    phone: $request->phone,
                    country: 'MY'
                )
                ->billingAddress(
                    streetAddress: $request->line1,
                    city: $request->city,
                    zipCode: $request->postcode,
                    state: $request->state,
                    country: 'MY'
                );

            // Add order items as products
            foreach ($order->items as $item) {
                $purchase->addProductCents(
                    name: $item->name,
                    priceInCents: $item->unit_price,
                    quantity: $item->quantity
                );
            }

            // Add shipping as a product if applicable
            if ($shippingCost > 0) {
                $purchase->addProductCents(
                    name: 'Shipping (' . ucfirst(str_replace('_', ' ', $request->shipping_method)) . ')',
                    priceInCents: $shippingCost,
                    quantity: 1
                );
            }

            // Apply discount using CHIP's total_discount_override
            if ($order->discount_total > 0) {
                $purchase->discount($order->discount_total);
            }

            // Represent tax explicitly so payment total matches order.
            if ($order->tax_total > 0) {
                $purchase->addProductCents(
                    name: 'Tax',
                    priceInCents: $order->tax_total,
                    quantity: 1
                );
            }

            // Set redirect URLs
            $purchase->redirects(
                successUrl: route('shop.payment.success', $order),
                failureUrl: route('shop.payment.failed', $order),
                cancelUrl: route('shop.payment.cancelled', $order)
            );

            // Set webhook URL
            $purchase->webhook(Chip::webhookUrl());

            // Store order metadata
            $purchase->metadata([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'affiliate_code' => session('affiliate_code'),
            ]);

            // Create the purchase and get the checkout URL
            $chipPurchase = $purchase->create();

            // Store CHIP purchase ID in order metadata
            $order->update([
                'metadata' => array_merge($order->metadata ?? [], [
                    'chip_purchase_id' => $chipPurchase->id,
                ]),
            ]);

            // Redirect to CHIP payment page
            return redirect()->away($chipPurchase->getCheckoutUrl());

        } catch (Exception $e) {
            // Log the error
            Log::error('CHIP payment creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            // Mark order as failed
            $order->update(['status' => 'payment_failed']);

            return redirect()->route('shop.checkout')
                ->with('error', 'Payment initialization failed. Please try again. Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle successful payment redirect from CHIP.
     */
    public function paymentSuccess(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        // Clear cart and session
        Cart::clear();
        Cart::clearConditions();
        session()->forget(['cart_count', 'applied_voucher', 'pending_order_id', 'pending_affiliate_code', 'pending_voucher_code']);

        // For demo: Simulate webhook if payment is still pending
        // In production, CHIP sends the webhook to a public URL automatically
        if ((string) $order->status === 'pending_payment' && ($order->metadata['chip_purchase_id'] ?? null)) {
            $this->simulatePaymentWebhook($order);
            $order->refresh(); // Reload to get updated status
        }

        $order->load('items', 'shippingAddress', 'billingAddress');

        return view('shop.payment-success', compact('order'));
    }

    /**
     * Handle failed payment redirect from CHIP.
     */
    public function paymentFailed(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        $order->update(['status' => 'payment_failed']);

        return view('shop.payment-failed', compact('order'));
    }

    /**
     * Handle cancelled payment redirect from CHIP.
     */
    public function paymentCancelled(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        $order->update(['status' => 'cancelled']);

        return view('shop.payment-cancelled', compact('order'));
    }

    /**
     * Order success page (for viewing existing completed orders).
     */
    public function orderSuccess(Order $order): View
    {
        $this->ensureOrderAccessible($order);

        $order->load('items', 'shippingAddress', 'billingAddress');

        return view('shop.order-success', compact('order'));
    }

    /**
     * Track affiliate from URL.
     */
    public function trackAffiliate(Request $request, string $code): RedirectResponse
    {
        $owner = OwnerContext::resolve();

        $affiliate = Affiliate::where('code', mb_strtoupper($code))
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', 'active')
            ->first();

        if ($affiliate) {
            session(['affiliate_code' => $affiliate->code]);

            // Track click
            $affiliate->increment('total_clicks');
        }

        return redirect()->route('shop.home');
    }

    /**
     * Buy now - direct checkout.
     */
    public function buyNow(Request $request): RedirectResponse
    {
        $request->validate([
            'product_id' => ['required', 'string'],
            'quantity' => 'required|integer|min:1',
        ]);

        $owner = OwnerContext::resolve();

        $product = Product::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereKey($request->product_id)
            ->firstOrFail();

        if (! $product->isInStock()) {
            return back()->with('error', 'Sorry, this product is out of stock.');
        }

        $quantity = max(1, (int) $request->quantity);

        /** @var PriceCalculator $priceCalculator */
        $priceCalculator = app(PriceCalculator::class);

        $priceResult = $priceCalculator->calculate($product, $quantity, [
            'currency' => 'MYR',
        ]);

        // Clear cart and add single item
        Cart::clear();
        Cart::clearConditions();

        Cart::add([
            'id' => $product->id,
            'name' => $product->name,
            'price' => $priceResult->finalPrice,
            'quantity' => $quantity,
            'attributes' => [
                'sku' => $product->sku,
                'category' => $product->categories->first()?->name,
                'slug' => $product->slug,
            ],
        ]);

        session(['cart_count' => Cart::getTotalQuantity()]);

        return redirect()->route('shop.checkout');
    }

    /**
     * My orders page.
     */
    public function orders(): View
    {
        $owner = OwnerContext::resolve();

        $orders = Order::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with('items', 'shippingAddress')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('shop.orders', compact('orders'));
    }

    /**
     * Account page.
     */
    public function account(): View | RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('shop.home')->with('error', 'Please sign in to view your account.');
        }

        $user = Auth::user();
        $owner = OwnerContext::resolve();

        $recentOrders = Order::query()
            ->when(
                $owner,
                fn ($query) => $query->forOwner($owner),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->with('items', 'shippingAddress')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('shop.account', compact('user', 'recentOrders'));
    }

    private function ensureOrderAccessible(Order $order): void
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        if ($order->owner_type === null || $order->owner_id === null) {
            abort(404);
        }

        if (
            $order->owner_type !== $owner->getMorphClass()
            || (string) $order->owner_id !== (string) $owner->getKey()
        ) {
            abort(404);
        }
    }

    private function ensureProductAccessible(Product $product): void
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            abort(404);
        }

        if ($product->owner_type === null || $product->owner_id === null) {
            abort(404);
        }

        if (
            $product->owner_type !== $owner->getMorphClass()
            || (string) $product->owner_id !== (string) $owner->getKey()
        ) {
            abort(404);
        }
    }

    /**
     * Order tracking page.
     */
    public function tracking(Request $request): View
    {
        $shipment = null;
        $recentShipments = null;

        $owner = OwnerContext::resolve();

        // Search for shipment if query provided
        $search = $request->get('tracking_number', $request->get('q', ''));

        if (is_string($search) && $search !== '') {
            $query = mb_strtoupper($search);

            $shipment = JntOrder::query()
                ->when(
                    $owner,
                    fn ($builder) => $builder->forOwner($owner),
                    fn ($builder) => $builder->whereRaw('1 = 0'),
                )
                ->where(function ($builder) use ($query): void {
                    $builder->where('tracking_number', $query)
                        ->orWhere('order_id', $query);
                })
                ->with('trackingEvents')
                ->first();
        }

        // Get recent shipments for display
        $recentShipments = JntOrder::query()
            ->when(
                $owner,
                fn ($builder) => $builder->forOwner($owner),
                fn ($builder) => $builder->whereRaw('1 = 0'),
            )
            ->whereNotNull('tracking_number')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('shop.tracking', compact('shipment', 'recentShipments'));
    }

    /**
     * Track shipment search (redirect).
     */
    public function trackingSearch(Request $request): RedirectResponse
    {
        $q = $request->get('tracking_number', '');

        return redirect()->route('shop.tracking', ['tracking_number' => $q]);
    }

    /**
     * Simulate CHIP payment webhook for demo purposes.
     * In production, CHIP sends webhooks to a public URL with signature verification.
     */
    private function simulatePaymentWebhook(Order $order): void
    {
        $shipping = $order->shippingAddress;
        $streetAddress = $shipping?->line1 ?? '';

        $simulator = WebhookSimulator::paid()
            ->purchaseId($order->metadata['chip_purchase_id'])
            ->reference($order->order_number)
            ->amount($order->grand_total)
            ->customer(
                $shipping?->email ?? 'demo@example.com',
                $shipping?->getFullName() ?? 'Demo Customer',
                $shipping?->phone ?? '+60123456789'
            )
            ->with([
                'client' => [
                    'street_address' => $streetAddress,
                    'city' => $shipping?->city ?? '',
                    'state' => $shipping?->state ?? '',
                    'zip_code' => $shipping?->postcode ?? '',
                    'country' => 'MY',
                    'shipping_street_address' => $streetAddress,
                    'shipping_city' => $shipping?->city ?? '',
                    'shipping_state' => $shipping?->state ?? '',
                    'shipping_zip_code' => $shipping?->postcode ?? '',
                    'shipping_country' => 'MY',
                ],
                'purchase' => [
                    'total' => $order->grand_total,
                    'currency' => 'MYR',
                    'metadata' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ],
                    'products' => $order->items->map(fn ($item) => [
                        'name' => $item->name,
                        'price' => $item->unit_price,
                        'quantity' => (string) $item->quantity,
                        'category' => 'product',
                        'discount' => 0,
                        'tax_percent' => '0.00',
                    ])->toArray(),
                    'subtotal_override' => $order->subtotal,
                    'total_discount_override' => $order->discount_total,
                    'shipping_options' => $order->shipping_total > 0 ? [
                        ['amount' => $order->shipping_total, 'title' => 'Shipping'],
                    ] : [],
                ],
            ])
            ->fpx()
            ->isTest();

        $payload = $simulator->getPayload();
        $purchase = $simulator->toPurchase();

        app(HandleChipPaymentSuccess::class)->handle(
            new PurchasePaid(
                purchase: $purchase,
                payload: $payload,
            ),
        );
    }
}
