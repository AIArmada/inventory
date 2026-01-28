<x-shop-layout title="Checkout">
    <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

        <form action="{{ route('shop.checkout.process') }}" method="POST">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Checkout Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Contact Information -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Contact Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" name="email" required
                                       value="{{ auth()->user()?->email ?? old('email') }}"
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" name="phone" required
                                       value="{{ old('phone') }}"
                                       placeholder="+60123456789"
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Address -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Address</h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" name="first_name" required
                                           value="{{ old('first_name') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <input type="text" name="last_name" required
                                           value="{{ old('last_name') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 1</label>
                                <input type="text" name="line1" required
                                    value="{{ old('line1') }}"
                                       placeholder="Street address"
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address Line 2 (Optional)</label>
                                <input type="text" name="line2"
                                    value="{{ old('line2') }}"
                                       placeholder="Apartment, suite, unit, etc."
                                       class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="col-span-2 md:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                    <input type="text" name="city" required
                                           value="{{ old('city') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                    <select name="state" required
                                            class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">Select</option>
                                        <option value="Johor">Johor</option>
                                        <option value="Kedah">Kedah</option>
                                        <option value="Kelantan">Kelantan</option>
                                        <option value="Melaka">Melaka</option>
                                        <option value="Negeri Sembilan">Negeri Sembilan</option>
                                        <option value="Pahang">Pahang</option>
                                        <option value="Perak">Perak</option>
                                        <option value="Perlis">Perlis</option>
                                        <option value="Pulau Pinang">Pulau Pinang</option>
                                        <option value="Sabah">Sabah</option>
                                        <option value="Sarawak">Sarawak</option>
                                        <option value="Selangor" selected>Selangor</option>
                                        <option value="Terengganu">Terengganu</option>
                                        <option value="Kuala Lumpur">Kuala Lumpur</option>
                                        <option value="Labuan">Labuan</option>
                                        <option value="Putrajaya">Putrajaya</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Postcode</label>
                                    <input type="text" name="postcode" required
                                           value="{{ old('postcode') }}"
                                           class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Shipping Method</h2>
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer border-amber-500 bg-amber-50">
                                <input type="radio" name="shipping_method" value="jnt_standard" checked
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900">J&T Standard</span>
                                        <span class="font-medium text-gray-900">RM 8.00</span>
                                    </div>
                                    <p class="text-sm text-gray-500">3-5 business days</p>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-amber-300">
                                <input type="radio" name="shipping_method" value="jnt_express"
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900">J&T Express</span>
                                        <span class="font-medium text-gray-900">RM 15.00</span>
                                    </div>
                                    <p class="text-sm text-gray-500">1-2 business days</p>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-amber-300">
                                <input type="radio" name="shipping_method" value="free"
                                       class="text-amber-500 focus:ring-amber-500"
                                       {{ $subtotal >= 10000 ? '' : 'disabled' }}>
                                <div class="ml-4 flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900 {{ $subtotal < 10000 ? 'text-gray-400' : '' }}">Free Shipping</span>
                                        <span class="font-medium text-green-600">FREE</span>
                                    </div>
                                    <p class="text-sm {{ $subtotal < 10000 ? 'text-red-500' : 'text-gray-500' }}">
                                        {{ $subtotal >= 10000 ? 'Available for orders over RM100' : 'Spend RM'.number_format((10000 - $subtotal) / 100, 2).' more to qualify' }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Payment Method</h2>
                        <p class="text-sm text-gray-600 mb-4">Powered by CHIP - Malaysia's trusted payment gateway</p>
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer border-amber-500 bg-amber-50">
                                <input type="radio" name="payment_method" value="fpx" checked
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex items-center gap-3">
                                    <span class="text-2xl">🏦</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Online Banking (FPX)</span>
                                        <p class="text-sm text-gray-500">Pay directly from your bank</p>
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-amber-300">
                                <input type="radio" name="payment_method" value="card"
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex items-center gap-3">
                                    <span class="text-2xl">💳</span>
                                    <div>
                                        <span class="font-medium text-gray-900">Credit/Debit Card</span>
                                        <p class="text-sm text-gray-500">Visa, Mastercard, AMEX</p>
                                    </div>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-amber-300">
                                <input type="radio" name="payment_method" value="ewallet"
                                       class="text-amber-500 focus:ring-amber-500">
                                <div class="ml-4 flex items-center gap-3">
                                    <span class="text-2xl">📱</span>
                                    <div>
                                        <span class="font-medium text-gray-900">E-Wallet</span>
                                        <p class="text-sm text-gray-500">Touch 'n Go, GrabPay, Boost</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Notes (Optional)</h2>
                        <textarea name="notes" rows="3" 
                                  placeholder="Special instructions for your order..."
                                  class="w-full border rounded-lg px-3 py-2 focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow p-6 sticky top-24">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>

                        <!-- Cart Items -->
                        <div class="space-y-4 max-h-64 overflow-y-auto mb-4">
                            @foreach($items as $item)
                            <div class="flex gap-3">
                                <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-xl">📦</div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 text-sm line-clamp-1">{{ $item->name }}</p>
                                    <p class="text-sm text-gray-500">Qty: {{ $item->quantity }}</p>
                                </div>
                                <p class="font-medium text-gray-900 text-sm">RM {{ number_format($item->getRawSubtotal() / 100, 2) }}</p>
                            </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <!-- Totals -->
                        <div class="space-y-3">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span>RM {{ number_format($subtotalWithoutConditions / 100, 2) }}</span>
                            </div>

                            @foreach($conditions as $condition)
                            @php $conditionValue = (float) $condition->getValue(); @endphp
                            <div class="flex justify-between {{ $conditionValue < 0 ? 'text-green-600' : 'text-gray-600' }}">
                                @php
                                    $conditionName = (string) $condition->getName();

                                    $displayConditionName = match (true) {
                                        str_starts_with($conditionName, 'voucher_') => 'Voucher Discount',
                                        str_starts_with($conditionName, 'affiliate_') => 'Affiliate Discount',
                                        default => $conditionName,
                                    };
                                @endphp
                                <span>{{ $displayConditionName }}</span>
                                <span>
                                    @if($conditionValue < 0)
                                        -RM {{ number_format(abs($conditionValue) / 100, 2) }}
                                    @else
                                        RM {{ number_format($conditionValue / 100, 2) }}
                                    @endif
                                </span>
                            </div>
                            @endforeach

                            <div class="flex justify-between text-gray-600" id="shipping-cost">
                                <span>Shipping</span>
                                <span>RM 8.00</span>
                            </div>

                            <hr>

                            <div class="flex justify-between text-xl font-bold text-gray-900">
                                <span>Total</span>
                                <span id="order-total">RM {{ number_format(($total + 800) / 100, 2) }}</span>
                            </div>
                        </div>

                        <!-- Applied Voucher -->
                        @if(session('applied_voucher'))
                        <div class="mt-4 p-3 bg-green-50 rounded-lg">
                            <p class="text-sm text-green-700">
                                ✓ Voucher <strong>{{ session('applied_voucher') }}</strong> applied
                            </p>
                        </div>
                        @endif

                        <!-- Affiliate -->
                        @if(session('affiliate_code'))
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-blue-700">
                                🤝 Affiliate: <strong>{{ session('affiliate_code') }}</strong>
                            </p>
                        </div>
                        @endif

                        <!-- Submit -->
                        <button type="submit" 
                                class="mt-6 w-full bg-amber-500 hover:bg-amber-600 text-white py-3 rounded-lg font-semibold text-lg transition">
                            Place Order
                        </button>

                        <!-- Security Badge -->
                        <div class="mt-4 text-center text-xs text-gray-500">
                            <p>🔒 Secure checkout powered by CHIP</p>
                            <p class="mt-1">Your payment is protected</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Update shipping cost based on selection
        document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const shippingCost = document.getElementById('shipping-cost').querySelector('span:last-child');
                const total = document.getElementById('order-total');
                const baseTotal = {{ $total }};
                
                let shipping = 800;
                if (this.value === 'jnt_express') {
                    shipping = 1500;
                    shippingCost.textContent = 'RM 15.00';
                } else if (this.value === 'free') {
                    shipping = 0;
                    shippingCost.textContent = 'FREE';
                    shippingCost.classList.add('text-green-600');
                } else {
                    shippingCost.textContent = 'RM 8.00';
                    shippingCost.classList.remove('text-green-600');
                }
                
                total.textContent = 'RM ' + ((baseTotal + shipping) / 100).toFixed(2);
            });
        });
    </script>
</x-shop-layout>
