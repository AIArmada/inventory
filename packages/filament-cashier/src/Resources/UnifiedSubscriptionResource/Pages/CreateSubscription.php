<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

final class CreateSubscription extends CreateRecord
{
    protected static string $resource = UnifiedSubscriptionResource::class;

    public function form(Schema $schema): Schema
    {
        $gatewayDetector = app(GatewayDetector::class);

        return $schema
            ->schema([
                Wizard::make([
                    Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.customer'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Select::make('user_id')
                                ->label(__('filament-cashier::subscriptions.create.customer_label'))
                                ->options(fn () => $this->getCustomerOptions())
                                ->searchable()
                                ->required()
                                ->live(),
                        ]),

                    Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.gateway'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Radio::make('gateway')
                                ->label(__('filament-cashier::subscriptions.create.gateway_label'))
                                ->options($gatewayDetector->getGatewayOptions())
                                ->descriptions([
                                    'stripe' => __('filament-cashier::subscriptions.create.gateway_stripe_description'),
                                    'chip' => __('filament-cashier::subscriptions.create.gateway_chip_description'),
                                ])
                                ->required()
                                ->live()
                                ->columns(2),
                        ]),

                    Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.plan'))
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            Section::make()
                                ->schema([
                                    TextInput::make('type')
                                        ->label('Subscription Type')
                                        ->default('default')
                                        ->required(),

                                    Select::make('plan_id')
                                        ->label(__('filament-cashier::subscriptions.create.plan_label'))
                                        ->options(fn (Get $get) => $this->getPlansForGateway($get('gateway')))
                                        ->searchable()
                                        ->required(),

                                    TextInput::make('quantity')
                                        ->label(__('filament-cashier::subscriptions.create.quantity_label'))
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(1),

                                    Toggle::make('has_trial')
                                        ->label(__('filament-cashier::subscriptions.create.has_trial_label'))
                                        ->live(),

                                    TextInput::make('trial_days')
                                        ->label(__('filament-cashier::subscriptions.create.trial_days_label'))
                                        ->numeric()
                                        ->default(14)
                                        ->minValue(1)
                                        ->visible(fn (Get $get): bool => (bool) $get('has_trial')),
                                ]),
                        ]),

                    Wizard\Step::make(__('filament-cashier::subscriptions.create.steps.payment'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Select::make('payment_method')
                                ->label(__('filament-cashier::subscriptions.create.payment_method_label'))
                                ->placeholder(__('filament-cashier::subscriptions.create.payment_method_placeholder'))
                                ->options(fn (Get $get) => $this->getPaymentMethodsForUser($get('user_id'), $get('gateway'))),
                        ]),
                ])
                    ->submitAction(new HtmlString(view('filament-cashier::components.wizard-submit-button')->render()))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function getCustomerOptions(): array
    {
        $billableModel = config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            return [];
        }

        return CashierOwnerScope::apply($billableModel::query())
            ->limit(100)
            ->get()
            ->mapWithKeys(fn ($user) => [
                $user->getKey() => $user->name ?? $user->email ?? (string) $user->getKey(),
            ])
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    protected function getPlansForGateway(?string $gateway): array
    {
        if ($gateway === null) {
            return [];
        }

        // Try to get plans from config
        $configPlans = config("cashier.gateways.{$gateway}.plans", []);

        if (! empty($configPlans)) {
            return $configPlans;
        }

        // Return some common defaults for demo purposes
        return match ($gateway) {
            'stripe' => [
                'price_basic_monthly' => 'Basic Monthly - $9/mo',
                'price_pro_monthly' => 'Pro Monthly - $29/mo',
                'price_premium_monthly' => 'Premium Monthly - $99/mo',
                'price_basic_yearly' => 'Basic Yearly - $90/yr',
                'price_pro_yearly' => 'Pro Yearly - $290/yr',
            ],
            'chip' => [
                'plan_basic_monthly' => 'Basic Monthly - RM 39/mo',
                'plan_pro_monthly' => 'Pro Monthly - RM 99/mo',
                'plan_premium_monthly' => 'Premium Monthly - RM 299/mo',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    protected function getPaymentMethodsForUser(?string $userId, ?string $gateway): array
    {
        if ($userId === null || $gateway === null) {
            return [];
        }

        $gatewayDetector = app(GatewayDetector::class);

        if (! $gatewayDetector->isAvailable($gateway)) {
            return [];
        }

        $billableModel = config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            return [];
        }

        $user = CashierOwnerScope::apply($billableModel::query())
            ->whereKey($userId)
            ->first();

        if ($user === null) {
            return [];
        }

        // Try to get payment methods from the gateway
        try {
            if ($gateway === 'stripe' && method_exists($user, 'paymentMethods')) {
                return $user->paymentMethods()
                    ->mapWithKeys(fn ($pm) => [
                        $pm->id => ($pm->card->brand ?? 'Card') . ' **** ' . ($pm->card->last4 ?? '****'),
                    ])
                    ->toArray();
            }

            if ($gateway === 'chip' && method_exists($user, 'chipPaymentMethods')) {
                return $user->chipPaymentMethods()
                    ->mapWithKeys(fn ($pm) => [
                        $pm->id => $pm->type . ' **** ' . ($pm->last4 ?? '****'),
                    ])
                    ->toArray();
            }
        } catch (Throwable) {
            // Silently fail if gateway API is not configured
        }

        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $billableModel = config('cashier.models.billable', 'App\\Models\\User');
        $user = CashierOwnerScope::apply($billableModel::query())
            ->whereKey($data['user_id'])
            ->first();

        if ($user === null) {
            throw new AuthorizationException('Selected customer is not accessible.');
        }

        if (! $user instanceof BillableContract) {
            throw new RuntimeException('Configured cashier billable model must implement BillableContract.');
        }

        $gateway = $data['gateway'];

        $gatewayDetector = app(GatewayDetector::class);

        if (! $gatewayDetector->isAvailable($gateway)) {
            throw new RuntimeException("Selected gateway is not available: {$gateway}");
        }

        // Build the subscription using the unified cashier interface
        if (class_exists(Cashier::class)) {
            $builder = Cashier::gateway($gateway)
                ->newSubscription($user, $data['type'] ?? 'default', $data['plan_id']);

            if (isset($data['quantity']) && $data['quantity'] > 1) {
                $builder->quantity($data['quantity']);
            }

            if (! empty($data['has_trial']) && ! empty($data['trial_days'])) {
                $builder->trialDays((int) $data['trial_days']);
            }

            if (! empty($data['payment_method'])) {
                $builder->create($data['payment_method']);

                return $user;
            }

            $builder->create();

            return $user;
        }

        // Fallback for direct gateway usage
        if ($gateway === 'stripe' && method_exists($user, 'newSubscription')) {
            $builder = $user->newSubscription($data['type'] ?? 'default', $data['plan_id']);

            if (isset($data['quantity']) && $data['quantity'] > 1) {
                $builder->quantity($data['quantity']);
            }

            if (! empty($data['has_trial']) && ! empty($data['trial_days'])) {
                $builder->trialDays((int) $data['trial_days']);
            }

            if (! empty($data['payment_method'])) {
                return $builder->create($data['payment_method']);
            }

            return $builder->create();
        }

        throw new RuntimeException("Unable to create subscription on gateway: {$gateway}");
    }

    protected function getCreatedNotification(): ?Notification
    {
        $gateway = $this->data['gateway'] ?? 'unknown';

        return Notification::make()
            ->title(__('filament-cashier::subscriptions.create.success', [
                'gateway' => ucfirst($gateway),
            ]))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
