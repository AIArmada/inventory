<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\Discovery\PageTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use Filament\Pages\Page;

beforeEach(function (): void {
    test()->transformer = new PageTransformer;
});

describe('PageTransformer → transform', function (): void {
    it('throws exception for non-existent class', function (): void {
        $transformer = test()->transformer;

        expect(fn () => $transformer->transform('NonExistentClass'))
            ->toThrow(InvalidArgumentException::class, 'Invalid page class');
    });

    it('throws exception for non-page class', function (): void {
        $transformer = test()->transformer;

        expect(fn () => $transformer->transform(stdClass::class))
            ->toThrow(InvalidArgumentException::class, 'Invalid page class');
    });

    it('transforms a valid page class', function (): void {
        $pageClass = createMockPageClass('TestPage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result)->toBeInstanceOf(DiscoveredPage::class)
            ->and($result->fqcn)->toBe($pageClass)
            ->and($result->permissions)->toBeArray()
            ->and($result->permissions)->toContain('viewTestPage');
    });

    it('includes panel in transformed page', function (): void {
        $pageClass = createMockPageClass('PanelTestPage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass, 'admin');

        expect($result->panel)->toBe('admin');
    });
});

describe('PageTransformer → permission generation', function (): void {
    it('generates view permission based on class name', function (): void {
        $pageClass = createMockPageClass('DashboardPage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result->permissions)->toContain('viewDashboardPage');
    });

    it('generates unique permission for each page', function (): void {
        $page1Class = createMockPageClass('SettingsPage');
        $page2Class = createMockPageClass('ReportsPage');

        $transformer = test()->transformer;
        $result1 = $transformer->transform($page1Class);
        $result2 = $transformer->transform($page2Class);

        expect($result1->permissions)->toContain('viewSettingsPage')
            ->and($result2->permissions)->toContain('viewReportsPage')
            ->and($result1->permissions)->not->toContain('viewReportsPage');
    });
});

describe('PageTransformer → metadata extraction', function (): void {
    it('extracts metadata with default values', function (): void {
        $pageClass = createMockPageClass('MetadataPage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result->metadata)->toBeArray()
            ->and($result->metadata)->toHaveKey('hasForm')
            ->and($result->metadata)->toHaveKey('hasTable')
            ->and($result->metadata)->toHaveKey('isWizard')
            ->and($result->metadata)->toHaveKey('isDashboard')
            ->and($result->metadata)->toHaveKey('hasWidgets');
    });

    it('detects form method presence', function (): void {
        $pageClass = createMockPageClassWithForm('FormPage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result->metadata['hasForm'])->toBeTrue();
    });

    it('detects table method presence', function (): void {
        $pageClass = createMockPageClassWithTable('TablePage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result->metadata['hasTable'])->toBeTrue();
    });

    it('detects dashboard pages', function (): void {
        $pageClass = createMockDashboardPage('CustomDashboard');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result->metadata['isDashboard'])->toBeTrue();
    });

    it('detects widget methods', function (): void {
        $pageClass = createMockPageClassWithWidgets('WidgetPage');

        $transformer = test()->transformer;
        $result = $transformer->transform($pageClass);

        expect($result->metadata['hasWidgets'])->toBeTrue();
    });
});

/**
 * Helper function to create a mock page class for testing.
 */
function createMockPageClass(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockPages';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Pages\\Page {
                protected string \$view = 'test-view';
                protected static ?string \$slug = '" . mb_strtolower($className) . "';

                public static function getCluster(): ?string {
                    return null;
                }
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock page class with form method.
 */
function createMockPageClassWithForm(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockPages';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Pages\\Page {
                protected string \$view = 'test-view';
                protected static ?string \$slug = '" . mb_strtolower($className) . "';

                public static function getCluster(): ?string {
                    return null;
                }

                public function form(\\Filament\\Forms\\Form \$form): \\Filament\\Forms\\Form {
                    return \$form;
                }
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock page class with table method.
 */
function createMockPageClassWithTable(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockPages';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Pages\\Page {
                protected string \$view = 'test-view';
                protected static ?string \$slug = '" . mb_strtolower($className) . "';

                public static function getCluster(): ?string {
                    return null;
                }

                public function table(\\Filament\\Tables\\Table \$table): \\Filament\\Tables\\Table {
                    return \$table;
                }
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock dashboard page.
 */
function createMockDashboardPage(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockPages';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Pages\\Dashboard {
                protected static ?string \$slug = '" . mb_strtolower($className) . "';

                public static function getCluster(): ?string {
                    return null;
                }
            }
        ");
    }

    return $fullClassName;
}

/**
 * Helper function to create a mock page class with widgets.
 */
function createMockPageClassWithWidgets(string $className): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockPages';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Pages\\Page {
                protected string \$view = 'test-view';
                protected static ?string \$slug = '" . mb_strtolower($className) . "';

                public static function getCluster(): ?string {
                    return null;
                }

                protected function getHeaderWidgets(): array {
                    return [];
                }
            }
        ");
    }

    return $fullClassName;
}
