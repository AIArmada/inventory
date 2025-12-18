<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\Discovery\WidgetTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use Filament\Widgets\Widget;

beforeEach(function (): void {
    test()->transformer = new WidgetTransformer;
});

describe('WidgetTransformer → transform', function (): void {
    it('throws exception for non-existent class', function (): void {
        $transformer = test()->transformer;

        expect(fn () => $transformer->transform('NonExistentClass'))
            ->toThrow(InvalidArgumentException::class, 'Invalid widget class');
    });

    it('throws exception for non-widget class', function (): void {
        $transformer = test()->transformer;

        expect(fn () => $transformer->transform(stdClass::class))
            ->toThrow(InvalidArgumentException::class, 'Invalid widget class');
    });

    it('transforms a valid widget class', function (): void {
        $widgetClass = createMockWidgetClass('TestWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result)->toBeInstanceOf(DiscoveredWidget::class)
            ->and($result->fqcn)->toBe($widgetClass)
            ->and($result->permissions)->toBeArray()
            ->and($result->permissions)->toContain('viewTestWidget');
    });

    it('includes panel in transformed widget', function (): void {
        $widgetClass = createMockWidgetClass('PanelTestWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass, 'admin');

        expect($result->panel)->toBe('admin');
    });

    it('generates snake_case name from class', function (): void {
        $widgetClass = createMockWidgetClass('UserStatsWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->name)->toBe('user_stats_widget');
    });
});

describe('WidgetTransformer → widget type detection', function (): void {
    it('detects custom widget type', function (): void {
        $widgetClass = createMockWidgetClass('CustomWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->type)->toBe('custom');
    });

    it('detects chart widget type', function (): void {
        $widgetClass = createMockChartWidget('SalesChart');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->type)->toBe('chart');
    });

    it('detects stats widget type', function (): void {
        $widgetClass = createMockStatsWidget('DashboardStats');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->type)->toBe('stats');
    });
});

describe('WidgetTransformer → metadata extraction', function (): void {
    it('extracts metadata with default values', function (): void {
        $widgetClass = createMockWidgetClass('MetadataWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->metadata)->toBeArray()
            ->and($result->metadata)->toHaveKey('isChart')
            ->and($result->metadata)->toHaveKey('isStats')
            ->and($result->metadata)->toHaveKey('isLivewire')
            ->and($result->metadata)->toHaveKey('isPolling')
            ->and($result->metadata)->toHaveKey('chartType');
    });

    it('marks all widgets as livewire components', function (): void {
        $widgetClass = createMockWidgetClass('LivewireWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->metadata['isLivewire'])->toBeTrue();
    });

    it('detects chart widgets in metadata', function (): void {
        $widgetClass = createMockChartWidget('ChartMetadataWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->metadata['isChart'])->toBeTrue()
            ->and($result->metadata['isStats'])->toBeFalse();
    });

    it('detects stats widgets in metadata', function (): void {
        $widgetClass = createMockStatsWidget('StatsMetadataWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->metadata['isStats'])->toBeTrue()
            ->and($result->metadata['isChart'])->toBeFalse();
    });

    it('detects polling widgets', function (): void {
        $widgetClass = createMockWidgetWithPolling('PollingWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->metadata['isPolling'])->toBeTrue();
    });
});

describe('WidgetTransformer → permission generation', function (): void {
    it('generates view permission based on class name', function (): void {
        $widgetClass = createMockWidgetClass('OrderStatsWidget');

        $transformer = test()->transformer;
        $result = $transformer->transform($widgetClass);

        expect($result->permissions)->toContain('viewOrderStatsWidget');
    });

    it('generates unique permission for each widget', function (): void {
        $widget1Class = createMockWidgetClass('SalesWidget');
        $widget2Class = createMockWidgetClass('RevenueWidget');

        $transformer = test()->transformer;
        $result1 = $transformer->transform($widget1Class);
        $result2 = $transformer->transform($widget2Class);

        expect($result1->permissions)->toContain('viewSalesWidget')
            ->and($result2->permissions)->toContain('viewRevenueWidget')
            ->and($result1->permissions)->not->toContain('viewRevenueWidget');
    });
});

/**
 * Helper function to create a mock widget class for testing.
 */
function createMockWidgetClass(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockWidgets';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Widgets\\Widget {
                protected string \$view = 'test-view';
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock chart widget.
 */
function createMockChartWidget(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockWidgets';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Widgets\\ChartWidget {
                protected ?string \$heading = 'Chart';

                protected function getType(): string {
                    return 'line';
                }

                protected function getData(): array {
                    return [];
                }
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock stats widget.
 */
function createMockStatsWidget(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockWidgets';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Widgets\\StatsOverviewWidget {
                protected function getStats(): array {
                    return [];
                }
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock widget with polling.
 */
function createMockWidgetWithPolling(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockWidgets';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Widgets\\Widget {
                protected string \$view = 'test-view';
                protected static ?string \$pollingInterval = '30s';
            }
        ");
    }

    return $fullClassName;
}
