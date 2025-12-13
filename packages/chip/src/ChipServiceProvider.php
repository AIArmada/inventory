<?php

declare(strict_types=1);

namespace AIArmada\Chip;

use AIArmada\Chip\Clients\ChipCollectClient;
use AIArmada\Chip\Clients\ChipSendClient;
use AIArmada\Chip\Commands\AggregateMetricsCommand;
use AIArmada\Chip\Commands\ChipHealthCheckCommand;
use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\ProcessRecurringCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Events\WebhookReceived;
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Listeners\StoreWebhookData;
use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\Chip\Services\ChipSendService;
use AIArmada\Chip\Services\RecurringService;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Chip\Support\DocsIntegrationRegistrar;
use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ChipServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('chip')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasCommands([
                ChipHealthCheckCommand::class,
                ProcessRecurringCommand::class,
                RetryWebhooksCommand::class,
                CleanWebhooksCommand::class,
                AggregateMetricsCommand::class,
            ]);
    }

    public function configureWebhookRoutes(): void
    {
        if (! config('chip.webhooks.enabled', true)) {
            return;
        }

        Route::middleware(config('chip.webhooks.middleware', ['api']))
            ->group(fn () => $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php'));
    }

    public function packageRegistered(): void
    {
        $this->registerOwnerResolver();
        $this->registerServices();
        $this->registerClients();
        $this->registerGateway();
        $this->registerMiddleware();
    }

    public function packageBooted(): void
    {
        $this->validateConfiguration('chip', [
            'collect.api_key',
            'collect.brand_id',
        ]);

        $this->configureWebhookRoutes();
        $this->registerEventListeners();
        $this->bootDocsIntegration();
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            ChipCollectService::class,
            ChipSendService::class,
            WebhookService::class,
            RecurringService::class,
            ChipCollectClient::class,
            ChipSendClient::class,
            ChipGateway::class,
            PaymentGatewayInterface::class,
            OwnerResolverInterface::class,
            'chip.collect',
            'chip.send',
            'chip.gateway',
            'chip.recurring',
        ];
    }

    protected function bootDocsIntegration(): void
    {
        $registrar = new DocsIntegrationRegistrar;
        $registrar->register();
    }

    protected function registerOwnerResolver(): void
    {
        /** @var class-string<OwnerResolverInterface> $resolverClass */
        $resolverClass = config('chip.owner.resolver', NullOwnerResolver::class);

        $this->app->singleton(OwnerResolverInterface::class, $resolverClass);
    }

    protected function registerMiddleware(): void
    {
        $this->app->singleton(VerifyWebhookSignature::class, function ($app): VerifyWebhookSignature {
            return new VerifyWebhookSignature(
                $app->make(WebhookService::class)
            );
        });
    }

    protected function registerEventListeners(): void
    {
        if (config('chip.webhooks.store_data', true)) {
            Event::listen(WebhookReceived::class, StoreWebhookData::class);
        }
    }

    protected function registerServices(): void
    {
        $this->app->singleton(ChipCollectService::class, function ($app): ChipCollectService {
            return new ChipCollectService(
                $app->make(ChipCollectClient::class),
                $app->make(CacheRepository::class)
            );
        });

        $this->app->singleton(ChipSendService::class, function ($app): ChipSendService {
            return new ChipSendService(
                $app->make(ChipSendClient::class)
            );
        });

        $this->app->singleton(WebhookService::class, function ($app): WebhookService {
            return new WebhookService;
        });

        $this->app->singleton(RecurringService::class, function ($app): RecurringService {
            return new RecurringService(
                $app->make(ChipCollectService::class)
            );
        });

        $this->app->alias(ChipCollectService::class, 'chip.collect');
        $this->app->alias(ChipSendService::class, 'chip.send');
        $this->app->alias(WebhookService::class, 'chip.webhook');
        $this->app->alias(RecurringService::class, 'chip.recurring');
    }

    protected function registerClients(): void
    {
        $this->app->singleton(ChipCollectClient::class, function (): ChipCollectClient {
            $apiKey = config('chip.collect.api_key');
            $brandId = config('chip.collect.brand_id');

            $baseUrlConfig = config('chip.collect.base_url', 'https://gate.chip-in.asia/api/v1/');
            $environment = config('chip.environment', 'sandbox');

            if (is_array($baseUrlConfig)) {
                $baseUrl = $baseUrlConfig[$environment] ?? reset($baseUrlConfig);
            } else {
                $baseUrl = $baseUrlConfig;
            }

            return new ChipCollectClient(
                $apiKey,
                $brandId,
                (string) $baseUrl,
                config('chip.http.timeout', 30),
                config('chip.http.retry', [
                    'attempts' => 3,
                    'delay' => 1000,
                ])
            );
        });

        $this->app->singleton(ChipSendClient::class, function (): ChipSendClient {
            $apiKey = config('chip.send.api_key');
            $apiSecret = config('chip.send.api_secret');

            $environment = config('chip.environment', 'sandbox');

            return new ChipSendClient(
                apiKey: $apiKey,
                apiSecret: $apiSecret,
                environment: $environment,
                baseUrl: config("chip.send.base_url.{$environment}")
                    ?? config('chip.send.base_url.sandbox', 'https://staging-api.chip-in.asia/api'),
                timeout: config('chip.http.timeout', 30),
                retryConfig: config('chip.http.retry', [
                    'attempts' => 3,
                    'delay' => 1000,
                ])
            );
        });
    }

    protected function registerGateway(): void
    {
        $this->app->singleton(ChipGateway::class, function ($app): ChipGateway {
            return new ChipGateway(
                $app->make(ChipCollectService::class),
                $app->make(WebhookService::class)
            );
        });

        // Register as the default PaymentGatewayInterface if no other is bound
        // This allows other packages to swap it with their own implementation
        if (! $this->app->bound(PaymentGatewayInterface::class)) {
            $this->app->bind(PaymentGatewayInterface::class, ChipGateway::class);
        }

        $this->app->alias(ChipGateway::class, 'chip.gateway');
    }
}
