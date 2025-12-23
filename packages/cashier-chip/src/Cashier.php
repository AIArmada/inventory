<?php

declare(strict_types=1);

namespace AIArmada\CashierChip;

use AIArmada\CashierChip\Contracts\BillableContract;
use AIArmada\CashierChip\Testing\FakeChipClient;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Main Cashier class for CHIP payment gateway.
 *
 * Can be referenced as either `Cashier` or `CashierChip` for compatibility.
 */
class Cashier
{
    /**
     * The Cashier Chip library version.
     */
    public const VERSION = '1.0.0';

    /**
     * Indicates if Cashier routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     */
    public static bool $deactivatePastDue = true;

    /**
     * Indicates if Cashier will mark incomplete subscriptions as inactive.
     */
    public static bool $deactivateIncomplete = true;

    /**
     * The default customer model class name.
     */
    public static string $customerModel = 'App\\Models\\User';

    /**
     * The subscription model class name.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The subscription item model class name.
     */
    public static string $subscriptionItemModel = SubscriptionItem::class;

    /**
     * The custom currency formatter.
     */
    /**
     * @var callable(int, ?string, ?string, array<string, mixed>): string|null
     */
    protected static $formatCurrencyUsing = null;

    /**
     * The fake CHIP service for testing.
     */
    protected static ?FakeChipCollectService $fakeChip = null;

    /**
     * Indicates if the fake service is enabled.
     */
    protected static bool $isFake = false;

    /**
     * Get the customer instance by its CHIP ID.
     *
     * @phpstan-return (Model&BillableContract)|null
     */
    public static function findBillable(?string $chipId): ?Model
    {
        if (! $chipId) {
            return null;
        }

        $model = static::$customerModel;

        return $model::where('chip_id', $chipId)->first();
    }

    /**
     * Resolve a billable from a CHIP client id in system contexts (webhooks/events).
     *
     * This explicitly bypasses owner scoping when the current owner is not resolvable,
     * since webhook/event payloads are not tenant-aware.
     *
     * @phpstan-return (Model&BillableContract)|null
     */
    public static function findBillableForWebhook(?string $chipId): ?Model
    {
        if (! $chipId) {
            return null;
        }

        $model = static::$customerModel;

        $query = $model::query();

        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return null;
        }

        return $query->where('chip_id', $chipId)->first();
    }

    public static function findSubscriptionForWebhook(Model $billable, string $subscriptionType): ?Subscription
    {
        $query = Subscription::query()
            ->where('user_id', $billable->getKey())
            ->where('type', $subscriptionType);

        if ((bool) config('cashier-chip.features.owner.enabled', true) && OwnerContext::resolve() === null) {
            return null;
        }

        return $query->first();
    }

    /**
     * Get the CHIP Collect service client.
     */
    public static function chip(): ChipCollectService | FakeChipCollectService
    {
        if (static::$isFake && static::$fakeChip) {
            return static::$fakeChip;
        }

        return app(ChipCollectService::class);
    }

    /**
     * Enable fake CHIP client for testing.
     */
    public static function fake(?FakeChipClient $fakeClient = null): FakeChipCollectService
    {
        static::$isFake = true;
        static::$fakeChip = new FakeChipCollectService($fakeClient);

        return static::$fakeChip;
    }

    /**
     * Get the fake CHIP service.
     */
    public static function getFake(): ?FakeChipCollectService
    {
        return static::$fakeChip;
    }

    /**
     * Determine if the fake service is enabled.
     */
    public static function isFake(): bool
    {
        return static::$isFake;
    }

    /**
     * Disable fake CHIP client and restore real service.
     */
    public static function unfake(): void
    {
        static::$isFake = false;
        static::$fakeChip = null;
    }

    /**
     * Reset the fake service state.
     */
    public static function resetFake(): void
    {
        if (static::$fakeChip) {
            static::$fakeChip->reset();
        }
    }

    /**
     * Set the custom currency formatter.
     */
    public static function formatCurrencyUsing(?callable $callback): void
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  array<string, mixed>  $options
     */
    public static function formatAmount(int $amount, ?string $currency = null, ?string $locale = null, array $options = []): string
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency, $locale, $options);
        }

        $currency = mb_strtoupper($currency ?? config('cashier-chip.currency', 'MYR'));
        $locale = $locale ?? config('cashier-chip.currency_locale', 'ms_MY');

        // Akaunting\Money expects amount in cents/minor units
        $money = new Money($amount, new Currency($currency), false);

        return $money->format($locale);
    }

    /**
     * Configure Cashier to not register its routes.
     */
    public static function ignoreRoutes(): static
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     */
    public static function keepPastDueSubscriptionsActive(): static
    {
        static::$deactivatePastDue = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain incomplete subscriptions as active.
     */
    public static function keepIncompleteSubscriptionsActive(): static
    {
        static::$deactivateIncomplete = false;

        return new static;
    }

    /**
     * Set the customer model class name.
     *
     * @param  class-string<Model>  $customerModel
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     *
     * @param  class-string<Model>  $subscriptionModel
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the subscription item model class name.
     *
     * @param  class-string<Model>  $subscriptionItemModel
     */
    public static function useSubscriptionItemModel(string $subscriptionItemModel): void
    {
        static::$subscriptionItemModel = $subscriptionItemModel;
    }
}
