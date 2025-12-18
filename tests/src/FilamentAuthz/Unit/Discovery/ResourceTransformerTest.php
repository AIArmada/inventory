<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\Discovery\ResourceTransformer;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use Filament\Resources\Resource;

beforeEach(function (): void {
    test()->transformer = new ResourceTransformer;
});

describe('ResourceTransformer → transform', function (): void {
    it('throws exception for non-existent class', function (): void {
        $transformer = test()->transformer;

        expect(fn () => $transformer->transform('NonExistentClass'))
            ->toThrow(InvalidArgumentException::class, 'Invalid resource class');
    });

    it('throws exception for non-resource class', function (): void {
        $transformer = test()->transformer;

        expect(fn () => $transformer->transform(stdClass::class))
            ->toThrow(InvalidArgumentException::class, 'Invalid resource class');
    });

    it('transforms a valid resource class', function (): void {
        // Create a mock resource class for testing
        $resourceClass = createMockResourceClass('TestResource', 'App\\Models\\TestModel');

        $transformer = test()->transformer;
        $result = $transformer->transform($resourceClass);

        expect($result)->toBeInstanceOf(DiscoveredResource::class)
            ->and($result->fqcn)->toBe($resourceClass)
            ->and($result->permissions)->toBeArray()
            ->and($result->permissions)->toContain('viewAny')
            ->and($result->permissions)->toContain('view')
            ->and($result->permissions)->toContain('create')
            ->and($result->permissions)->toContain('update')
            ->and($result->permissions)->toContain('delete');
    });

    it('includes panel in transformed resource', function (): void {
        $resourceClass = createMockResourceClass('PanelTestResource', 'App\\Models\\PanelTest');

        $transformer = test()->transformer;
        $result = $transformer->transform($resourceClass, 'admin');

        expect($result->panel)->toBe('admin');
    });
});

describe('ResourceTransformer → base permissions', function (): void {
    it('includes all standard CRUD permissions', function (): void {
        $resourceClass = createMockResourceClass('CrudResource', 'App\\Models\\Crud');

        $transformer = test()->transformer;
        $result = $transformer->transform($resourceClass);

        $expectedPermissions = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

        foreach ($expectedPermissions as $permission) {
            expect($result->permissions)->toContain($permission);
        }
    });
});

describe('ResourceTransformer → metadata extraction', function (): void {
    it('extracts metadata with default values', function (): void {
        $resourceClass = createMockResourceClass('MetadataResource', 'App\\Models\\Metadata');

        $transformer = test()->transformer;
        $result = $transformer->transform($resourceClass);

        expect($result->metadata)->toBeArray()
            ->and($result->metadata)->toHaveKey('hasRelations')
            ->and($result->metadata)->toHaveKey('hasBulkActions')
            ->and($result->metadata)->toHaveKey('hasCustomActions')
            ->and($result->metadata)->toHaveKey('hasSoftDeletes')
            ->and($result->metadata)->toHaveKey('isGlobalSearch');
    });
});

describe('ResourceTransformer → custom action detection', function (): void {
    it('identifies non-standard actions as custom', function (): void {
        $transformer = test()->transformer;

        // Using reflection to test protected method
        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('isCustomAction');
        $method->setAccessible(true);

        expect($method->invoke($transformer, 'export'))->toBeTrue()
            ->and($method->invoke($transformer, 'approve'))->toBeTrue()
            ->and($method->invoke($transformer, 'publish'))->toBeTrue();
    });

    it('identifies standard actions as non-custom', function (): void {
        $transformer = test()->transformer;

        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('isCustomAction');
        $method->setAccessible(true);

        expect($method->invoke($transformer, 'view'))->toBeFalse()
            ->and($method->invoke($transformer, 'edit'))->toBeFalse()
            ->and($method->invoke($transformer, 'delete'))->toBeFalse()
            ->and($method->invoke($transformer, 'restore'))->toBeFalse()
            ->and($method->invoke($transformer, 'forceDelete'))->toBeFalse()
            ->and($method->invoke($transformer, 'replicate'))->toBeFalse();
    });
});

/**
 * Helper function to create a mock resource class for testing.
 */
function createMockResourceClass(string $className, string $modelClass): string
{
    $namespace = 'AIArmada\\FilamentAuthz\\Tests\\MockResources';
    $fullClassName = "{$namespace}\\{$className}";

    if (! class_exists($fullClassName)) {
        // Create a dynamic mock model class if it doesn't exist
        if (! class_exists($modelClass)) {
            $modelParts = explode('\\', $modelClass);
            $modelName = array_pop($modelParts);
            $modelNamespace = implode('\\', $modelParts);

            eval("
                namespace {$modelNamespace};
                class {$modelName} extends \\Illuminate\\Database\\Eloquent\\Model {
                    protected \$fillable = ['name'];
                }
            ");
        }

        // Create a dynamic mock resource class
        eval("
            namespace {$namespace};
            class {$className} extends \\Filament\\Resources\\Resource {
                protected static ?string \$model = '{$modelClass}';
                protected static string|\\UnitEnum|null \$navigationGroup = 'Test';
                protected static ?string \$navigationLabel = '{$className}';
                protected static ?string \$slug = '" . mb_strtolower($className) . "';

                public static function getRelations(): array {
                    return [];
                }

                public static function getCluster(): ?string {
                    return null;
                }
            }
        ");
    }

    return $fullClassName;
}
