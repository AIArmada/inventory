<?php

declare(strict_types=1);

namespace App\Providers;

use AIArmada\Chip\Events\PurchasePaid;
use App\Listeners\HandleChipPaymentSuccess;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'order' => Order::class,
            'product' => Product::class,
            'user' => User::class,
            'permission' => Permission::class,
            'role' => Role::class,
        ]);

        // Register CHIP webhook listeners for order processing
        Event::listen(PurchasePaid::class, HandleChipPaymentSuccess::class);

        FilamentTimezone::set('Asia/Kuala_Lumpur');

    }
}
