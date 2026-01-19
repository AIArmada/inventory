<x-shop-layout title="Home">
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 overflow-hidden">
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="relative max-w-7xl mx-auto px-4 py-24 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
                Welcome to AIArmada Shop
            </h1>
            <p class="text-xl text-white/90 mb-8 max-w-2xl mx-auto">
                Experience the full power of our commerce packages. Browse products, add to cart, 
                apply vouchers, and complete checkout with real integrations.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('shop.products') }}" 
                   class="bg-white text-amber-600 px-8 py-3 rounded-lg font-semibold text-lg hover:bg-gray-100 transition">
                    Shop Now
                </a>
                <a href="#features" 
                   class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold text-lg hover:bg-white/10 transition">
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- Quick Actions Bar -->
    <section class="bg-gray-900 text-white py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-center gap-6 text-sm">
                <a href="{{ route('shop.tracking') }}" class="flex items-center gap-2 hover:text-amber-400 transition">
                    <span>📦</span> Track Your Order
                </a>
                <a href="{{ route('shop.orders') }}" class="flex items-center gap-2 hover:text-amber-400 transition">
                    <span>📋</span> Order History
                </a>
                <a href="/admin" class="flex items-center gap-2 hover:text-amber-400 transition">
                    <span>⚙️</span> Admin Panel
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Categories -->
    <section class="max-w-7xl mx-auto px-4 py-16 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Shop by Category</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach($categories as $category)
            <a href="{{ route('shop.products', ['category' => $category->slug]) }}" 
               class="group relative bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition">
                <div class="aspect-square bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                    <span class="text-6xl">
                        @switch($category->name)
                            @case('Electronics') 📱 @break
                            @case('Fashion') 👗 @break
                            @case('Home & Living') 🏠 @break
                            @case('Sports') ⚽ @break
                            @case('Beauty') 💄 @break
                            @case('Books') 📚 @break
                            @default 📦
                        @endswitch
                    </span>
                </div>
                <div class="p-4 text-center">
                    <h3 class="font-semibold text-gray-900 group-hover:text-amber-600 transition">{{ $category->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $category->products_count ?? 0 }} products</p>
                </div>
            </a>
            @endforeach
        </div>
    </section>

    <!-- Featured Products -->
    <section class="bg-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Featured Products</h2>
                <a href="{{ route('shop.products') }}" class="text-amber-600 hover:text-amber-700 font-medium">
                    View All →
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                <div class="group bg-white border rounded-2xl overflow-hidden hover:shadow-lg transition">
                    <div class="relative aspect-square bg-gray-100">
                        <div class="absolute inset-0 flex items-center justify-center text-6xl">
                            @if(str_contains(strtolower($product->name), 'phone') || str_contains(strtolower($product->name), 'iphone'))
                                📱
                            @elseif(str_contains(strtolower($product->name), 'laptop') || str_contains(strtolower($product->name), 'macbook'))
                                💻
                            @elseif(str_contains(strtolower($product->name), 'watch'))
                                ⌚
                            @elseif(str_contains(strtolower($product->name), 'airpod') || str_contains(strtolower($product->name), 'headphone'))
                                🎧
                            @elseif(str_contains(strtolower($product->name), 'dress') || str_contains(strtolower($product->name), 'shirt'))
                                👗
                            @elseif(str_contains(strtolower($product->name), 'chair') || str_contains(strtolower($product->name), 'lamp'))
                                🪑
                            @else
                                📦
                            @endif
                        </div>
                        @if($product->compare_price && $product->compare_price > $product->price)
                        <span class="absolute top-3 left-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                            SALE
                        </span>
                        @endif
                        @if(! $product->isInStock())
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span class="bg-white text-gray-900 px-4 py-2 rounded-lg font-semibold">Out of Stock</span>
                        </div>
                        @endif
                    </div>
                    <div class="p-4">
                        <p class="text-xs text-gray-500 mb-1">{{ $product->categories->first()?->name ?? 'Uncategorized' }}</p>
                        <h3 class="font-semibold text-gray-900 group-hover:text-amber-600 transition mb-2">
                            <a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a>
                        </h3>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-lg font-bold text-amber-600">RM {{ number_format($product->price / 100, 2) }}</span>
                            @if($product->compare_price && $product->compare_price > $product->price)
                            <span class="text-sm text-gray-400 line-through">RM {{ number_format($product->compare_price / 100, 2) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs {{ $product->isInStock() ? 'text-green-600' : 'text-red-600' }}">
                                {{ $product->isInStock() ? '✓ In Stock' : '✗ Out of Stock' }}
                            </span>
                        </div>
                        <form action="{{ route('shop.cart.add') }}" method="POST" class="mt-3">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" 
                                    {{ ! $product->isInStock() ? 'disabled' : '' }}
                                    class="w-full bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white py-2 rounded-lg font-medium transition">
                                Add to Cart
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="bg-gray-900 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-12">Powered by AIArmada Commerce</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white/10 rounded-2xl p-6 backdrop-blur">
                    <div class="text-4xl mb-4">🛒</div>
                    <h3 class="text-xl font-semibold mb-2">Smart Cart System</h3>
                    <p class="text-gray-300">Full-featured shopping cart with conditions, discounts, and real-time calculations.</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-6 backdrop-blur">
                    <div class="text-4xl mb-4">🎟️</div>
                    <h3 class="text-xl font-semibold mb-2">Voucher Engine</h3>
                    <p class="text-gray-300">Apply discount codes, automatic promotions, and percentage/fixed discounts.</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-6 backdrop-blur">
                    <div class="text-4xl mb-4">📦</div>
                    <h3 class="text-xl font-semibold mb-2">Inventory Management</h3>
                    <p class="text-gray-300">Multi-location inventory with reservations, allocations, and low inventory alerts.</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-6 backdrop-blur">
                    <div class="text-4xl mb-4">🤝</div>
                    <h3 class="text-xl font-semibold mb-2">Affiliate Program</h3>
                    <p class="text-gray-300">Partner tracking, commission calculations, and payout management.</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-6 backdrop-blur">
                    <div class="text-4xl mb-4">💳</div>
                    <h3 class="text-xl font-semibold mb-2">Payment Integration</h3>
                    <p class="text-gray-300">CHIP payment gateway with FPX, cards, and e-wallets support.</p>
                </div>
                <div class="bg-white/10 rounded-2xl p-6 backdrop-blur">
                    <div class="text-4xl mb-4">🚚</div>
                    <h3 class="text-xl font-semibold mb-2">Shipping Integration</h3>
                    <p class="text-gray-300">J&T Express integration with tracking and webhook updates.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Active Vouchers -->
    @if($activeVouchers->count() > 0)
    <section class="max-w-7xl mx-auto px-4 py-16 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">🎉 Active Promotions</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($activeVouchers as $voucher)
            <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-2xl p-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
                <div class="relative">
                    <p class="text-white/80 text-sm mb-1">Use code:</p>
                    <p class="text-2xl font-bold font-mono mb-2">{{ $voucher->code }}</p>
                    <p class="text-lg">
                        @if($voucher->type === \AIArmada\Vouchers\Enums\VoucherType::Percentage)
                            {{ rtrim(rtrim(number_format($voucher->value / 100, 2), '0'), '.') }}% OFF
                        @elseif($voucher->type === \AIArmada\Vouchers\Enums\VoucherType::Fixed)
                            RM {{ number_format($voucher->value / 100, 2) }} OFF
                        @elseif($voucher->type === \AIArmada\Vouchers\Enums\VoucherType::FreeShipping)
                            Free Shipping
                        @else
                            {{ $voucher->type->label() }}
                        @endif
                    </p>
                    @if($voucher->min_cart_value)
                    <p class="text-sm text-white/80 mt-2">Min. order: RM {{ number_format($voucher->min_cart_value / 100, 2) }}</p>
                    @endif
                    @if($voucher->expires_at)
                    <p class="text-xs text-white/60 mt-2">Expires: {{ $voucher->expires_at->format('M d, Y') }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    <!-- CTA -->
    <section class="bg-amber-50 py-16">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to Start Shopping?</h2>
            <p class="text-gray-600 mb-8">Browse our collection and experience the seamless checkout flow.</p>
            <a href="{{ route('shop.products') }}" 
               class="inline-block bg-amber-500 hover:bg-amber-600 text-white px-8 py-3 rounded-lg font-semibold text-lg transition">
                Browse All Products
            </a>
        </div>
    </section>
</x-shop-layout>
