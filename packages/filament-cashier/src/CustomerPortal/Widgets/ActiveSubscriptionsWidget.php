<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Widgets;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class ActiveSubscriptionsWidget extends Widget
{
    protected string $view = 'filament-cashier::customer-portal.widgets.active-subscriptions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public int $perGatewayLimit = 5;

    /**
     * @return Collection<int, UnifiedSubscription>
     */
    public function getSubscriptions(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $subscriptions = collect();
        $detector = app(GatewayDetector::class);
        $limit = max(1, $this->perGatewayLimit);

        if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $stripeSubscriptions = CashierOwnerScope::apply(Subscription::query())
                ->with('items')
                ->where('user_id', $user->getAuthIdentifier())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub));

            $subscriptions = $subscriptions->merge($stripeSubscriptions);
        }

        if ($detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $chipSubscriptions = CashierOwnerScope::apply($subscriptionModel::query())
                ->with('items')
                ->where('user_id', $user->getAuthIdentifier())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn ($sub) => UnifiedSubscription::fromChip($sub));

            $subscriptions = $subscriptions->merge($chipSubscriptions);
        }

        return $subscriptions->filter(fn (UnifiedSubscription $sub) => $sub->status->isActive());
    }
}
