<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support\Integrations;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Jnt\Cart\CartManagerWithJntShipping;
use AIArmada\Jnt\Cart\JntShippingCalculator;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Contracts\Foundation\Application;

/**
 * Registers JNT shipping integration with the Cart package.
 *
 * This registrar extends the CartManager with JNT shipping functionality
 * using the decorator pattern, allowing shipping address management
 * and rate calculation through the cart.
 */
final class CartIntegrationRegistrar
{
    public function __construct(private readonly Application $app) {}

    /**
     * Register the cart integration.
     */
    public function register(): void
    {
        if (! class_exists(CartManager::class)) {
            return;
        }

        $this->app->extend('cart', function (CartManagerInterface $manager, Application $app) {
            if ($manager instanceof CartManagerWithJntShipping) {
                return $manager;
            }

            $proxy = CartManagerWithJntShipping::fromCartManager($manager);

            // Inject the calculator if JntExpressService is available
            if ($app->bound(JntExpressService::class)) {
                $calculator = new JntShippingCalculator($app->make(JntExpressService::class));
                $proxy->setCalculator($calculator);
            }

            $app->instance(CartManager::class, $proxy);
            $app->instance(CartManagerInterface::class, $proxy);

            if (class_exists(\AIArmada\Cart\Facades\Cart::class)) {
                \AIArmada\Cart\Facades\Cart::clearResolvedInstance('cart');
            }

            return $proxy;
        });
    }
}
