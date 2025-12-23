<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Pages;

use AIArmada\Chip\Chip;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Stripe\Account;
use Stripe\Stripe;

final class GatewayManagement extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament-cashier::pages.gateway-management';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::gateway.management.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentCashierPlugin::get()->getNavigationGroup();
    }

    public function getTitle(): string
    {
        return __('filament-cashier::gateway.management.title');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function getGatewayDetector(): GatewayDetector
    {
        return app(GatewayDetector::class);
    }

    /**
     * Get gateway health status.
     *
     * @return Collection<int, array{gateway: string, label: string, color: string, icon: string, status: string, statusColor: string, lastCheck: string|null, message: string|null}>
     */
    public function getGatewayHealth(): Collection
    {
        $detector = $this->getGatewayDetector();
        $gateways = $detector->availableGateways();

        return collect($gateways)->map(function (string $gateway) use ($detector) {
            $health = $this->checkGatewayHealth($gateway);

            return [
                'gateway' => $gateway,
                'label' => $detector->getLabel($gateway),
                'color' => $detector->getColor($gateway),
                'icon' => $detector->getIcon($gateway),
                'status' => $health['status'],
                'statusColor' => $health['color'],
                'lastCheck' => now()->format('Y-m-d H:i:s'),
                'message' => $health['message'],
            ];
        });
    }

    /**
     * Get default gateway.
     */
    public function getDefaultGateway(): ?string
    {
        $cached = cache()->get('filament-cashier.default_gateway');

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return config('cashier.default');
    }

    /**
     * Test gateway connection action.
     */
    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label(__('filament-cashier::gateway.actions.test_connection'))
            ->icon('heroicon-o-signal')
            ->form([
                Forms\Components\Select::make('gateway')
                    ->label(__('filament-cashier::gateway.fields.gateway'))
                    ->options($this->getGatewayDetector()->getGatewayOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $health = $this->checkGatewayHealth($data['gateway']);
                $label = $this->getGatewayDetector()->getLabel($data['gateway']);

                if ($health['status'] === 'healthy') {
                    Notification::make()
                        ->success()
                        ->title(__('filament-cashier::gateway.notifications.connection_success', ['gateway' => $label]))
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title(__('filament-cashier::gateway.notifications.connection_failed', ['gateway' => $label]))
                        ->body($health['message'])
                        ->send();
                }
            });
    }

    /**
     * Set default gateway action.
     */
    public function setDefaultAction(): Action
    {
        return Action::make('setDefault')
            ->label(__('filament-cashier::gateway.actions.set_default'))
            ->icon('heroicon-o-star')
            ->form([
                Forms\Components\Select::make('gateway')
                    ->label(__('filament-cashier::gateway.fields.gateway'))
                    ->options($this->getGatewayDetector()->getGatewayOptions())
                    ->default($this->getDefaultGateway())
                    ->required(),
            ])
            ->action(function (array $data): void {
                // Store in cache for runtime configuration
                cache()->forever('filament-cashier.default_gateway', $data['gateway']);

                Notification::make()
                    ->success()
                    ->title(__('filament-cashier::gateway.notifications.default_set', [
                        'gateway' => $this->getGatewayDetector()->getLabel($data['gateway']),
                    ]))
                    ->send();
            });
    }

    /**
     * Check health of a specific gateway.
     *
     * @return array{status: string, color: string, message: string|null}
     */
    protected function checkGatewayHealth(string $gateway): array
    {
        try {
            if ($gateway === 'stripe') {
                return $this->checkStripeHealth();
            }

            if ($gateway === 'chip') {
                return $this->checkChipHealth();
            }

            return [
                'status' => 'unknown',
                'color' => 'gray',
                'message' => __('filament-cashier::gateway.health.unknown'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'color' => 'danger',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Stripe API health.
     *
     * @return array{status: string, color: string, message: string|null}
     */
    protected function checkStripeHealth(): array
    {
        if (! config('services.stripe.key') || ! config('services.stripe.secret')) {
            return [
                'status' => 'not_configured',
                'color' => 'warning',
                'message' => __('filament-cashier::gateway.health.not_configured'),
            ];
        }

        try {
            if (class_exists(Stripe::class)) {
                Stripe::setApiKey(config('services.stripe.secret'));
                Account::retrieve();

                return [
                    'status' => 'healthy',
                    'color' => 'success',
                    'message' => null,
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'color' => 'danger',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'unknown',
            'color' => 'gray',
            'message' => __('filament-cashier::gateway.health.sdk_missing'),
        ];
    }

    /**
     * Check CHIP API health.
     *
     * @return array{status: string, color: string, message: string|null}
     */
    protected function checkChipHealth(): array
    {
        if (! config('chip.brand_id') || ! config('chip.api_key')) {
            return [
                'status' => 'not_configured',
                'color' => 'warning',
                'message' => __('filament-cashier::gateway.health.not_configured'),
            ];
        }

        try {
            if (class_exists(Chip::class)) {
                $chip = app(Chip::class);
                // Simple health check - get brands
                $chip->brands()->first();

                return [
                    'status' => 'healthy',
                    'color' => 'success',
                    'message' => null,
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'color' => 'danger',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'unknown',
            'color' => 'gray',
            'message' => __('filament-cashier::gateway.health.sdk_missing'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->testConnectionAction(),
            $this->setDefaultAction(),
        ];
    }
}
