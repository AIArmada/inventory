<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt;

use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentJntPlugin implements Plugin
{
    protected ?bool $hasOrders = null;

    protected ?bool $hasTrackingEvents = null;

    protected ?bool $hasWebhookLogs = null;

    protected ?bool $hasWidgets = null;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-jnt';
    }

    /**
     * Enable/disable JNT Orders resource.
     */
    public function orders(bool $condition = true): static
    {
        $this->hasOrders = $condition;

        return $this;
    }

    /**
     * Enable/disable Tracking Events resource.
     */
    public function trackingEvents(bool $condition = true): static
    {
        $this->hasTrackingEvents = $condition;

        return $this;
    }

    /**
     * Enable/disable Webhook Logs resource.
     */
    public function webhookLogs(bool $condition = true): static
    {
        $this->hasWebhookLogs = $condition;

        return $this;
    }

    /**
     * Enable/disable dashboard widgets.
     */
    public function widgets(bool $condition = true): static
    {
        $this->hasWidgets = $condition;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $widgets = [];

        /** @var array<string, bool> $features */
        $features = config('filament-jnt.features', []);

        if ($this->hasOrders ?? ($features['orders'] ?? true)) {
            $resources[] = JntOrderResource::class;
        }

        if ($this->hasTrackingEvents ?? ($features['tracking_events'] ?? true)) {
            $resources[] = JntTrackingEventResource::class;
        }

        if ($this->hasWebhookLogs ?? ($features['webhook_logs'] ?? true)) {
            $resources[] = JntWebhookLogResource::class;
        }

        if ($this->hasWidgets ?? ($features['widgets'] ?? true)) {
            $widgets[] = JntStatsWidget::class;
        }

        $panel
            ->resources($resources)
            ->widgets($widgets);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
