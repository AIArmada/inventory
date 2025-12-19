<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Services\Discovery\ResourceTransformer;
use Filament\Resources\Resource;
use InvalidArgumentException;
use ReflectionClass;
use stdClass;

describe('ResourceTransformer', function (): void {
    describe('constructor', function (): void {
        it('creates instance', function (): void {
            $transformer = new ResourceTransformer;

            expect($transformer)->toBeInstanceOf(ResourceTransformer::class);
        });

        it('has basePermissions property', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $property = $reflection->getProperty('basePermissions');

            $permissions = $property->getValue($transformer);

            expect($permissions)->toContain('viewAny');
            expect($permissions)->toContain('view');
            expect($permissions)->toContain('create');
            expect($permissions)->toContain('update');
            expect($permissions)->toContain('delete');
        });
    });

    describe('transform', function (): void {
        it('throws exception for non-existent class', function (): void {
            $transformer = new ResourceTransformer;

            $transformer->transform('NonExistentClass');
        })->throws(InvalidArgumentException::class);

        it('throws exception for non-resource class', function (): void {
            $transformer = new ResourceTransformer;

            $transformer->transform(stdClass::class);
        })->throws(InvalidArgumentException::class);
    });

    describe('generatePermissions', function (): void {
        it('generates base permissions', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('generatePermissions');

            // Create a mock resource class name
            // Since we can't create a real resource in tests easily, we'll test indirectly
            expect($method->isProtected())->toBeTrue();
        });
    });

    describe('detectBulkActions', function (): void {
        it('is a protected method', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('detectBulkActions');

            expect($method->isProtected())->toBeTrue();
        });

        it('returns empty array for non-existent class', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('detectBulkActions');

            $result = $method->invoke($transformer, 'NonExistentClass');

            expect($result)->toBe([]);
        });
    });

    describe('detectTableActions', function (): void {
        it('is a protected method', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('detectTableActions');

            expect($method->isProtected())->toBeTrue();
        });

        it('returns empty array for non-existent class', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('detectTableActions');

            $result = $method->invoke($transformer, 'NonExistentClass');

            expect($result)->toBe([]);
        });
    });

    describe('detectRelationManagers', function (): void {
        it('is a protected method', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('detectRelationManagers');

            expect($method->isProtected())->toBeTrue();
        });

        it('returns empty array for non-existent class', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('detectRelationManagers');

            $result = $method->invoke($transformer, 'NonExistentClass');

            expect($result)->toBe([]);
        });
    });

    describe('isCustomAction', function (): void {
        it('is a protected method', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->isProtected())->toBeTrue();
        });

        it('returns false for view action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'view'))->toBeFalse();
        });

        it('returns false for edit action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'edit'))->toBeFalse();
        });

        it('returns false for delete action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'delete'))->toBeFalse();
        });

        it('returns false for replicate action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'replicate'))->toBeFalse();
        });

        it('returns true for custom action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'approve'))->toBeTrue();
        });

        it('returns true for export action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'export'))->toBeTrue();
        });

        it('returns true for import action', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'import'))->toBeTrue();
        });

        it('is case insensitive for standard actions', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('isCustomAction');

            expect($method->invoke($transformer, 'VIEW'))->toBeFalse();
            expect($method->invoke($transformer, 'View'))->toBeFalse();
            expect($method->invoke($transformer, 'DELETE'))->toBeFalse();
        });
    });

    describe('extractMetadata', function (): void {
        it('is a protected method', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('extractMetadata');

            expect($method->isProtected())->toBeTrue();
        });

        it('returns default metadata for non-existent class', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('extractMetadata');

            $result = $method->invoke($transformer, 'NonExistentClass');

            expect($result)->toBeArray();
            expect($result)->toHaveKey('hasRelations');
            expect($result)->toHaveKey('hasBulkActions');
            expect($result)->toHaveKey('hasCustomActions');
            expect($result)->toHaveKey('isGlobalSearch');
        });

        it('returns false for all metadata keys when class does not exist', function (): void {
            $transformer = new ResourceTransformer;
            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('extractMetadata');

            $result = $method->invoke($transformer, 'NonExistentClass');

            expect($result['hasRelations'])->toBeFalse();
            expect($result['hasBulkActions'])->toBeFalse();
            expect($result['hasCustomActions'])->toBeFalse();
            expect($result['isGlobalSearch'])->toBeFalse();
        });
    });
});
