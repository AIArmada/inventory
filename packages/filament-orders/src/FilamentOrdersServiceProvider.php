<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentOrders\Support\FilamentOrdersCache;
use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FilamentOrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-orders');

        Order::saved(static function (Order $order): void {
            FilamentOrdersCache::forgetForOrder($order);
        });

        Order::deleted(static function (Order $order): void {
            FilamentOrdersCache::forgetForOrder($order);
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-orders'),
            ], 'filament-orders-views');
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', FilamentAuthenticate::class])
            ->group(function (): void {
                Route::get('/orders/{order}/invoice/download', function (string $order) {
                    if ((bool) config('orders.owner.enabled', true) && OwnerContext::resolve() === null) {
                        abort(404);
                    }

                    $record = Order::query()
                        ->forOwner(includeGlobal: (bool) config('orders.owner.include_global', false))
                        ->findOrFail($order);

                    $user = Filament::auth()->user();

                    abort_unless($user && Gate::forUser($user)->allows('view', $record), 403);

                    return app(GenerateInvoice::class)->download($record);
                })->name('filament-orders.invoice.download');
            });
    }
}
