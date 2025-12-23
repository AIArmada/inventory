<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Policies\SubscriptionPolicy;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class ManageSubscriptions extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 1;

    public int $perGatewayLimit = 50;

    public bool $hasMoreSubscriptions = false;

    private const int DEFAULT_LOAD_MORE_INCREMENT = 50;

    protected string $view = 'filament-cashier::customer-portal.manage-subscriptions';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.subscriptions.title');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.subscriptions.title');
    }

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
        $fetchLimit = $limit + 1;

        $hasMoreStripe = false;
        $hasMoreChip = false;

        // Get Stripe subscriptions for this user
        if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $stripeModels = CashierOwnerScope::apply(Subscription::query())
                ->with('items')
                ->where('user_id', $user->getAuthIdentifier())
                ->orderByDesc('created_at')
                ->limit($fetchLimit)
                ->get()
                ->values();

            $hasMoreStripe = $stripeModels->count() > $limit;

            $stripeSubscriptions = $stripeModels
                ->take($limit)
                ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub));

            $subscriptions = $subscriptions->merge($stripeSubscriptions);
        }

        // Get CHIP subscriptions for this user
        if ($detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $chipModels = CashierOwnerScope::apply($subscriptionModel::query())
                ->with('items')
                ->where('user_id', $user->getAuthIdentifier())
                ->orderByDesc('created_at')
                ->limit($fetchLimit)
                ->get()
                ->values();

            $hasMoreChip = $chipModels->count() > $limit;

            $chipSubscriptions = $chipModels
                ->take($limit)
                ->map(fn ($sub) => UnifiedSubscription::fromChip($sub));

            $subscriptions = $subscriptions->merge($chipSubscriptions);
        }

        $this->hasMoreSubscriptions = $hasMoreStripe || $hasMoreChip;

        return $subscriptions->sortByDesc('createdAt')->values();
    }

    public function loadMoreSubscriptions(int $increment = self::DEFAULT_LOAD_MORE_INCREMENT): void
    {
        $this->perGatewayLimit += max(1, $increment);
    }

    public function cancelSubscription(string $gateway, string $id): void
    {
        $subscription = $this->findSubscription($gateway, $id);

        if ($subscription === null || ! $subscription->status->isCancelable()) {
            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.cancel_error'))
                ->danger()
                ->send();

            return;
        }

        // Policy authorization check
        if (! $this->authorizeAction('cancel', $subscription)) {
            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.unauthorized'))
                ->danger()
                ->send();

            return;
        }

        if (method_exists($subscription->original, 'cancel')) {
            $subscription->original->cancel();

            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.cancel_success'))
                ->success()
                ->send();
        }
    }

    public function resumeSubscription(string $gateway, string $id): void
    {
        $subscription = $this->findSubscription($gateway, $id);

        if ($subscription === null || ! $subscription->status->isResumable()) {
            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.resume_error'))
                ->danger()
                ->send();

            return;
        }

        // Policy authorization check
        if (! $this->authorizeAction('resume', $subscription)) {
            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.unauthorized'))
                ->danger()
                ->send();

            return;
        }

        if (method_exists($subscription->original, 'resume')) {
            $subscription->original->resume();

            Notification::make()
                ->title(__('filament-cashier::portal.subscriptions.resume_success'))
                ->success()
                ->send();
        }
    }

    /**
     * Authorize an action using the SubscriptionPolicy.
     */
    protected function authorizeAction(string $ability, UnifiedSubscription $subscription): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $policy = app(SubscriptionPolicy::class);

        return match ($ability) {
            'cancel' => $policy->cancel($user, $subscription->original),
            'resume' => $policy->resume($user, $subscription->original),
            'view' => $policy->view($user, $subscription->original),
            'update' => $policy->update($user, $subscription->original),
            default => false,
        };
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_subscription')
                ->label(__('filament-cashier::portal.subscriptions.new'))
                ->icon('heroicon-o-plus')
                ->url(fn () => route('filament.billing.pages.new-subscription'))
                ->visible(fn () => config('filament-cashier.billing_portal.features.subscriptions', true)),
        ];
    }

    protected function findSubscription(string $gateway, string $id): ?UnifiedSubscription
    {
        $detector = app(GatewayDetector::class);
        $userId = auth()->id();

        if ($gateway === 'stripe' && $detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $sub = CashierOwnerScope::apply(Subscription::query())
                ->with('items')
                ->where('user_id', $userId)
                ->whereKey($id)
                ->first();

            if ($sub) {
                return UnifiedSubscription::fromStripe($sub);
            }
        }

        if ($gateway === 'chip' && $detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;
            $sub = CashierOwnerScope::apply($subscriptionModel::query())
                ->with('items')
                ->where('user_id', $userId)
                ->whereKey($id)
                ->first();

            if ($sub) {
                return UnifiedSubscription::fromChip($sub);
            }
        }

        return null;
    }
}
