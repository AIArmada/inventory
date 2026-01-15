<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentTaxPlugin implements Plugin
{
    protected ?bool $hasZones = null;

    protected ?bool $hasClasses = null;

    protected ?bool $hasRates = null;

    protected ?bool $hasExemptions = null;

    protected ?bool $hasWidgets = null;

    protected ?bool $hasSettingsPage = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-tax';
    }

    /**
     * Enable/disable Tax Zones resource.
     */
    public function zones(bool $condition = true): static
    {
        $this->hasZones = $condition;

        return $this;
    }

    /**
     * Enable/disable Tax Classes resource.
     */
    public function classes(bool $condition = true): static
    {
        $this->hasClasses = $condition;

        return $this;
    }

    /**
     * Enable/disable Tax Rates resource.
     */
    public function rates(bool $condition = true): static
    {
        $this->hasRates = $condition;

        return $this;
    }

    /**
     * Enable/disable Tax Exemptions resource.
     */
    public function exemptions(bool $condition = true): static
    {
        $this->hasExemptions = $condition;

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

    /**
     * Enable/disable settings page.
     */
    public function settingsPage(bool $condition = true): static
    {
        $this->hasSettingsPage = $condition;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $widgets = [];
        $pages = [];

        /** @var array<string, bool> $features */
        $features = config('filament-tax.features', []);

        if ($this->hasZones ?? ($features['zones'] ?? true)) {
            $resources[] = Resources\TaxZoneResource::class;
        }

        if ($this->hasClasses ?? ($features['classes'] ?? true)) {
            $resources[] = Resources\TaxClassResource::class;
        }

        if ($this->hasRates ?? ($features['rates'] ?? true)) {
            $resources[] = Resources\TaxRateResource::class;
        }

        if ($this->hasExemptions ?? ($features['exemptions'] ?? true)) {
            $resources[] = Resources\TaxExemptionResource::class;
        }

        if ($this->hasWidgets ?? ($features['widgets'] ?? true)) {
            $widgets[] = Widgets\TaxStatsWidget::class;
            $widgets[] = Widgets\ExpiringExemptionsWidget::class;
            $widgets[] = Widgets\ZoneCoverageWidget::class;
        }

        $shouldShowSettings = $this->hasSettingsPage ?? ($features['settings_page'] ?? true);
        if ($shouldShowSettings && class_exists(\Filament\Pages\SettingsPage::class)) {
            $pages[] = Pages\ManageTaxSettings::class;
        }

        $panel
            ->resources($resources)
            ->widgets($widgets)
            ->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
