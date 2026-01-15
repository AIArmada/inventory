<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentCustomersPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-customers';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\CustomerResource::class,
                Resources\SegmentResource::class,
            ])
            ->pages([
                // Pages will be added here
            ])
            ->widgets([
                Widgets\CustomerStatsWidget::class,
                Widgets\RecentCustomersWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // Boot logic here
    }
}
