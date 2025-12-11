<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services\Discovery;

use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

class PageTransformer
{
    /**
     * Transform a page class into a DiscoveredPage.
     */
    public function transform(string $pageClass, ?string $panel = null): DiscoveredPage
    {
        if (! class_exists($pageClass) || ! is_subclass_of($pageClass, Page::class)) {
            throw new InvalidArgumentException("Invalid page class: {$pageClass}");
        }

        /** @var Page $page */
        $page = app($pageClass);

        $slug = null;
        $title = null;
        $cluster = null;

        try {
            $slug = $page::getSlug();
            $title = $page->getTitle();
            $cluster = $page::getCluster();
        } catch (Throwable) {
            // Some methods may not be available
        }

        return new DiscoveredPage(
            fqcn: $pageClass,
            title: $title,
            slug: $slug,
            cluster: $cluster,
            permissions: ['view' . class_basename($pageClass)],
            metadata: $this->extractMetadata($pageClass),
            panel: $panel,
        );
    }

    /**
     * Extract metadata from the page class.
     *
     * @return array<string, mixed>
     */
    protected function extractMetadata(string $pageClass): array
    {
        $metadata = [
            'hasForm' => false,
            'hasTable' => false,
            'isWizard' => false,
            'isDashboard' => false,
            'hasWidgets' => false,
        ];

        try {
            $reflection = new ReflectionClass($pageClass);

            $metadata['hasForm'] = $reflection->hasMethod('form');
            $metadata['hasTable'] = $reflection->hasMethod('table');
            $metadata['isWizard'] = is_subclass_of($pageClass, \Filament\Pages\BasePage::class)
                && $reflection->hasMethod('getSteps');
            $metadata['isDashboard'] = is_subclass_of($pageClass, Dashboard::class);

            // Check for widgets by looking at getHeaderWidgets or getFooterWidgets
            $metadata['hasWidgets'] = $reflection->hasMethod('getHeaderWidgets')
                || $reflection->hasMethod('getFooterWidgets')
                || $reflection->hasMethod('getWidgets');
        } catch (Throwable) {
            // Ignore reflection errors
        }

        return $metadata;
    }
}
