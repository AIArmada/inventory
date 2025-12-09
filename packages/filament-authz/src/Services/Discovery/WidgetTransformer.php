<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services\Discovery;

use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

class WidgetTransformer
{
    /**
     * Transform a widget class into a DiscoveredWidget.
     */
    public function transform(string $widgetClass, ?string $panel = null): DiscoveredWidget
    {
        if (! class_exists($widgetClass) || ! is_subclass_of($widgetClass, Widget::class)) {
            throw new InvalidArgumentException("Invalid widget class: {$widgetClass}");
        }

        return new DiscoveredWidget(
            fqcn: $widgetClass,
            name: Str::snake(class_basename($widgetClass)),
            type: $this->detectWidgetType($widgetClass),
            permissions: ['view'.class_basename($widgetClass)],
            metadata: $this->extractMetadata($widgetClass),
            panel: $panel,
        );
    }

    /**
     * Detect the type of widget.
     */
    protected function detectWidgetType(string $widgetClass): string
    {
        if (is_subclass_of($widgetClass, ChartWidget::class)) {
            return 'chart';
        }

        if (is_subclass_of($widgetClass, StatsOverviewWidget::class)) {
            return 'stats';
        }

        return 'custom';
    }

    /**
     * Extract metadata from the widget class.
     *
     * @return array<string, mixed>
     */
    protected function extractMetadata(string $widgetClass): array
    {
        $metadata = [
            'isChart' => false,
            'isStats' => false,
            'isLivewire' => true, // All Filament widgets are Livewire components
            'isPolling' => false,
            'chartType' => null,
        ];

        try {
            $metadata['isChart'] = is_subclass_of($widgetClass, ChartWidget::class);
            $metadata['isStats'] = is_subclass_of($widgetClass, StatsOverviewWidget::class);

            $reflection = new ReflectionClass($widgetClass);

            // Check for polling interval
            if ($reflection->hasProperty('pollingInterval')) {
                $prop = $reflection->getProperty('pollingInterval');
                $prop->setAccessible(true);
                $metadata['isPolling'] = $prop->getDefaultValue() !== null;
            }

            // Detect chart type for chart widgets
            if ($metadata['isChart'] && $reflection->hasMethod('getType')) {
                try {
                    /** @var ChartWidget $widget */
                    $widget = app($widgetClass);
                    $metadata['chartType'] = $widget->getType();
                } catch (Throwable) {
                    // Ignore
                }
            }
        } catch (Throwable) {
            // Ignore reflection errors
        }

        return $metadata;
    }
}
