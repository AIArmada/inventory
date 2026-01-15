<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs;

use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\QuickActionsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentDocsPlugin implements Plugin
{
    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    /** @var class-string|null */
    protected ?string $docResource = null;

    /** @var class-string|null */
    protected ?string $docTemplateResource = null;

    /** @var class-string|null */
    protected ?string $docSequenceResource = null;

    /** @var class-string|null */
    protected ?string $docEmailTemplateResource = null;

    protected bool $agingReportEnabled = true;

    protected bool $pendingApprovalsEnabled = true;

    protected bool $docStatsWidgetEnabled = true;

    protected bool $quickActionsWidgetEnabled = true;

    protected bool $recentDocumentsWidgetEnabled = true;

    protected bool $revenueChartWidgetEnabled = true;

    protected bool $statusBreakdownWidgetEnabled = true;

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-docs';
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * @param  class-string  $resource
     */
    public function docResource(string $resource): static
    {
        $this->docResource = $resource;

        return $this;
    }

    /**
     * @param  class-string  $resource
     */
    public function docTemplateResource(string $resource): static
    {
        $this->docTemplateResource = $resource;

        return $this;
    }

    /**
     * @param  class-string  $resource
     */
    public function docSequenceResource(string $resource): static
    {
        $this->docSequenceResource = $resource;

        return $this;
    }

    /**
     * @param  class-string  $resource
     */
    public function docEmailTemplateResource(string $resource): static
    {
        $this->docEmailTemplateResource = $resource;

        return $this;
    }

    public function agingReportEnabled(bool $enabled = true): static
    {
        $this->agingReportEnabled = $enabled;

        return $this;
    }

    public function pendingApprovalsEnabled(bool $enabled = true): static
    {
        $this->pendingApprovalsEnabled = $enabled;

        return $this;
    }

    public function docStatsWidgetEnabled(bool $enabled = true): static
    {
        $this->docStatsWidgetEnabled = $enabled;

        return $this;
    }

    public function quickActionsWidgetEnabled(bool $enabled = true): static
    {
        $this->quickActionsWidgetEnabled = $enabled;

        return $this;
    }

    public function recentDocumentsWidgetEnabled(bool $enabled = true): static
    {
        $this->recentDocumentsWidgetEnabled = $enabled;

        return $this;
    }

    public function revenueChartWidgetEnabled(bool $enabled = true): static
    {
        $this->revenueChartWidgetEnabled = $enabled;

        return $this;
    }

    public function statusBreakdownWidgetEnabled(bool $enabled = true): static
    {
        $this->statusBreakdownWidgetEnabled = $enabled;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup ?? config('filament-docs.navigation.group');
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function register(Panel $panel): void
    {
        $resources = array_filter([
            $this->docResource ?? DocResource::class,
            $this->docTemplateResource ?? DocTemplateResource::class,
            $this->docSequenceResource ?? DocSequenceResource::class,
            $this->docEmailTemplateResource ?? DocEmailTemplateResource::class,
        ]);

        $pages = array_filter([
            $this->agingReportEnabled ? AgingReportPage::class : null,
            $this->pendingApprovalsEnabled ? PendingApprovalsPage::class : null,
        ]);

        $widgets = array_filter([
            $this->quickActionsWidgetEnabled ? QuickActionsWidget::class : null,
            $this->recentDocumentsWidgetEnabled ? RecentDocumentsWidget::class : null,
            $this->statusBreakdownWidgetEnabled ? StatusBreakdownWidget::class : null,
            $this->revenueChartWidgetEnabled ? RevenueChartWidget::class : null,
            $this->docStatsWidgetEnabled ? DocStatsWidget::class : null,
        ]);

        $panel
            ->resources($resources)
            ->pages($pages)
            ->widgets($widgets);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
