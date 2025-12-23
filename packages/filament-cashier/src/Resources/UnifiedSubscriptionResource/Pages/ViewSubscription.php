<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Policies\SubscriptionPolicy;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Cashier\Subscription;

final class ViewSubscription extends ViewRecord
{
    protected static string $resource = UnifiedSubscriptionResource::class;

    protected ?UnifiedSubscription $subscription = null;

    public function mount(int | string $record): void
    {
        // Parse the composite key (gateway-id)
        [$gateway, $id] = explode('-', (string) $record, 2);

        $this->subscription = $this->resolveSubscription($gateway, $id);

        if ($this->subscription === null) {
            abort(404);
        }

        $user = auth()->user();

        if ($user === null || ! app(SubscriptionPolicy::class)->view($user, $this->subscription->original)) {
            throw new AuthorizationException('Not authorized to view this subscription.');
        }
    }

    public function infolist(Schema $schema): Schema
    {
        if ($this->subscription === null) {
            return $schema;
        }

        $sub = $this->subscription;

        return $schema
            ->state([
                'id' => $sub->id,
                'gateway' => $sub->gateway,
                'gateway_label' => $sub->gatewayConfig()['label'],
                'type' => $sub->type,
                'plan_id' => $sub->planId,
                'amount' => $sub->formattedAmount(),
                'currency' => $sub->currency,
                'quantity' => $sub->quantity,
                'status' => $sub->status->label(),
                'status_color' => $sub->status->color(),
                'status_icon' => $sub->status->icon(),
                'billing_cycle' => $sub->billingCycle(),
                'trial_ends_at' => $sub->trialEndsAt?->format('M d, Y'),
                'ends_at' => $sub->endsAt?->format('M d, Y'),
                'next_billing' => $sub->nextBillingDate?->format('M d, Y'),
                'created_at' => $sub->createdAt->format('M d, Y H:i'),
                'external_id' => $sub->getExternalId(),
            ])
            ->components([
                Section::make(__('filament-cashier::subscriptions.details.overview'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount')
                                    ->label('Amount')
                                    ->size('lg')
                                    ->weight('bold'),

                                TextEntry::make('quantity')
                                    ->label('Quantity'),

                                TextEntry::make('billing_cycle')
                                    ->label('Billing Cycle'),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn () => $sub->status->color()),

                                TextEntry::make('gateway_label')
                                    ->label('Gateway')
                                    ->badge()
                                    ->color(fn () => $sub->gatewayConfig()['color']),

                                TextEntry::make('type')
                                    ->label('Type'),

                                TextEntry::make('plan_id')
                                    ->label('Plan'),
                            ]),
                    ]),

                Section::make(__('filament-cashier::subscriptions.details.billing_info'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created'),

                                TextEntry::make('trial_ends_at')
                                    ->label('Trial Ends')
                                    ->placeholder('—'),

                                TextEntry::make('next_billing')
                                    ->label('Next Billing')
                                    ->placeholder('—'),

                                TextEntry::make('ends_at')
                                    ->label('Ends At')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make(__('filament-cashier::subscriptions.details.gateway_details', [
                    'gateway' => $sub->gatewayConfig()['label'],
                ]))
                    ->schema($this->getGatewayDetailsSchema()),
            ]);
    }

    public function getHeading(): string
    {
        if ($this->subscription === null) {
            return __('filament-cashier::subscriptions.details.title');
        }

        return $this->subscription->gatewayConfig()['label'] . ': ' . $this->subscription->type . ' Subscription';
    }

    public function getSubheading(): ?string
    {
        return $this->subscription?->planId;
    }

    protected function resolveSubscription(string $gateway, string $id): ?UnifiedSubscription
    {
        $detector = app(GatewayDetector::class);

        if ($gateway === 'stripe' && $detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            $sub = CashierOwnerScope::apply(Subscription::query())
                ->with('items')
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
                ->whereKey($id)
                ->first();
            if ($sub) {
                return UnifiedSubscription::fromChip($sub);
            }
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        if ($this->subscription === null) {
            return [];
        }

        return [
            Actions\Action::make('cancel')
                ->label(__('filament-cashier::subscriptions.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($this->subscription->status->isCancelable())
                ->requiresConfirmation()
                ->modalHeading(__('filament-cashier::subscriptions.actions.cancel_heading', [
                    'gateway' => $this->subscription->gatewayConfig()['label'],
                ]))
                ->modalDescription(__('filament-cashier::subscriptions.actions.cancel_description'))
                ->action(function (): void {
                    $user = auth()->user();

                    if ($this->subscription === null || $user === null || ! app(SubscriptionPolicy::class)->cancel($user, $this->subscription->original)) {
                        throw new AuthorizationException('Not authorized to cancel this subscription.');
                    }

                    if (method_exists($this->subscription->original, 'cancel')) {
                        $this->subscription->original->cancel();
                    }
                }),

            Actions\Action::make('resume')
                ->label(__('filament-cashier::subscriptions.actions.resume'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible($this->subscription->status->isResumable())
                ->action(function (): void {
                    $user = auth()->user();

                    if ($this->subscription === null || $user === null || ! app(SubscriptionPolicy::class)->resume($user, $this->subscription->original)) {
                        throw new AuthorizationException('Not authorized to resume this subscription.');
                    }

                    if (method_exists($this->subscription->original, 'resume')) {
                        $this->subscription->original->resume();
                    }
                }),

            Actions\Action::make('view_external')
                ->label(__('filament-cashier::subscriptions.actions.view_external', [
                    'gateway' => $this->subscription->gatewayConfig()['label'],
                ]))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url($this->subscription->externalDashboardUrl())
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function getGatewayDetailsSchema(): array
    {
        if ($this->subscription === null) {
            return [];
        }

        $sub = $this->subscription;

        if ($sub->gateway === 'stripe') {
            return [
                Grid::make(2)
                    ->schema([
                        TextEntry::make('external_id')
                            ->label(__('filament-cashier::subscriptions.details.subscription_id'))
                            ->copyable(),

                        TextEntry::make('stripe_customer_id')
                            ->label(__('filament-cashier::subscriptions.details.customer_id'))
                            ->state(fn () => $sub->original->stripe_id ?? $sub->original->user?->stripe_id ?? '—')
                            ->copyable(),
                    ]),
            ];
        }

        if ($sub->gateway === 'chip') {
            return [
                Grid::make(2)
                    ->schema([
                        TextEntry::make('external_id')
                            ->label(__('filament-cashier::subscriptions.details.subscription_id'))
                            ->copyable(),

                        TextEntry::make('chip_customer_id')
                            ->label(__('filament-cashier::subscriptions.details.customer_id'))
                            ->state(fn () => $sub->original->chip_client_id ?? $sub->original->user?->chip_id ?? '—')
                            ->copyable(),
                    ]),
            ];
        }

        return [
            TextEntry::make('external_id')
                ->label(__('filament-cashier::subscriptions.details.subscription_id'))
                ->copyable(),
        ];
    }
}
