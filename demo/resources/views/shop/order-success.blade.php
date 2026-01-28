<x-shop-layout title="Order Confirmed">
    <div class="max-w-3xl mx-auto px-4 py-16 sm:px-6 lg:px-8 text-center">
        <!-- Success Icon -->
        <div class="mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-8">
            <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-900 mb-4">Thank You for Your Order!</h1>
        <p class="text-xl text-gray-600 mb-2">Your order has been placed successfully.</p>
        <p class="text-gray-500 mb-8">Order Number: <span class="font-mono font-bold text-gray-900">{{ $order->order_number }}</span></p>

        <!-- Order Summary Card -->
        <div class="bg-white rounded-2xl shadow-lg p-8 text-left mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Order Summary</h2>

            <!-- Status -->
            <div class="flex items-center justify-between p-4 bg-amber-50 rounded-lg mb-6">
                <div>
                    <p class="text-sm text-gray-600">Order Status</p>
                    <p class="font-semibold text-amber-600">{{ $order->status->label() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Payment Status</p>
                    <p class="font-semibold text-amber-600">{{ $order->paid_at !== null ? 'Paid' : 'Pending' }}</p>
                </div>
            </div>

            <!-- Items -->
            <div class="space-y-4 mb-6">
                @foreach($order->items as $item)
                <div class="flex justify-between items-center py-3 border-b">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-xl">📦</div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $item->name }}</p>
                            <p class="text-sm text-gray-500">Qty: {{ $item->quantity }}</p>
                        </div>
                    </div>
                    <p class="font-medium text-gray-900">RM {{ number_format($item->total / 100, 2) }}</p>
                </div>
                @endforeach
            </div>

            <!-- Totals -->
            <div class="space-y-2 border-t pt-4">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span>RM {{ number_format($order->subtotal / 100, 2) }}</span>
                </div>
                @if($order->discount_total > 0)
                <div class="flex justify-between text-green-600">
                    <span>Discount</span>
                    <span>-RM {{ number_format($order->discount_total / 100, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between text-gray-600">
                    <span>Shipping</span>
                    <span>{{ $order->shipping_total > 0 ? 'RM '.number_format($order->shipping_total / 100, 2) : 'Free' }}</span>
                </div>
                @if($order->tax_total > 0)
                <div class="flex justify-between text-gray-600">
                    <span>Tax</span>
                    <span>RM {{ number_format($order->tax_total / 100, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between text-xl font-bold text-gray-900 pt-2 border-t">
                    <span>Total</span>
                    <span>RM {{ number_format($order->grand_total / 100, 2) }}</span>
                </div>
            </div>

            <!-- Shipping Info -->
            <div class="mt-6 pt-6 border-t">
                <h3 class="font-semibold text-gray-900 mb-3">Shipping Address</h3>
                <div class="text-gray-600">
                    <p>{{ $order->shippingAddress?->getFullName() ?? '' }}</p>
                    <p>{{ $order->shippingAddress?->line1 ?? '' }}</p>
                    @if(!empty($order->shippingAddress?->line2))
                    <p>{{ $order->shippingAddress?->line2 }}</p>
                    @endif
                    <p>{{ $order->shippingAddress?->city ?? '' }}, {{ $order->shippingAddress?->state ?? '' }} {{ $order->shippingAddress?->postcode ?? '' }}</p>
                    <p>{{ $order->shippingAddress?->country ?? '' }}</p>
                </div>
            </div>

            <!-- Voucher/Affiliate Info -->
            @if(($order->metadata['voucher_code'] ?? null) || ($order->metadata['affiliate_code'] ?? null))
            <div class="mt-6 pt-6 border-t grid grid-cols-2 gap-4">
                @if($order->metadata['voucher_code'] ?? null)
                <div class="p-3 bg-green-50 rounded-lg">
                    <p class="text-sm text-gray-600">Voucher Applied</p>
                    <p class="font-mono font-bold text-green-600">{{ $order->metadata['voucher_code'] }}</p>
                </div>
                @endif
                @if($order->metadata['affiliate_code'] ?? null)
                <div class="p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-gray-600">Affiliate Partner</p>
                    <p class="font-mono font-bold text-blue-600">{{ $order->metadata['affiliate_code'] }}</p>
                </div>
                @endif
            </div>
            @endif
        </div>

        <!-- What's Next -->
        <div class="bg-gray-50 rounded-2xl p-8 mb-8">
            <h3 class="font-bold text-gray-900 mb-4">What's Next?</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                <div>
                    <div class="text-3xl mb-2">📧</div>
                    <p class="font-medium text-gray-900">Confirmation Email</p>
                    <p class="text-sm text-gray-500">Check your inbox for order details</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">📦</div>
                    <p class="font-medium text-gray-900">Order Processing</p>
                    <p class="text-sm text-gray-500">We're preparing your order</p>
                </div>
                <div>
                    <div class="text-3xl mb-2">🚚</div>
                    <p class="font-medium text-gray-900">Shipping</p>
                    <p class="text-sm text-gray-500">J&T will deliver to you</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('shop.products') }}" 
               class="bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold transition">
                Continue Shopping
            </a>
            @auth
            <a href="{{ route('shop.orders') }}" 
               class="border-2 border-gray-300 hover:border-gray-400 text-gray-700 px-8 py-3 rounded-lg font-semibold transition">
                View All Orders
            </a>
            @endauth
        </div>

        <!-- Demo Note -->
        <div class="mt-12 p-4 bg-blue-50 rounded-lg">
            <p class="text-sm text-blue-700">
                <strong>🎭 Demo Note:</strong> This is a showcase of the AIArmada Commerce packages. 
                In production, you would be redirected to the CHIP payment gateway to complete payment.
            </p>
        </div>
    </div>
</x-shop-layout>
