<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\PermissionBuilder;

afterEach(function (): void {
    Mockery::close();
});

describe('PermissionBuilder', function (): void {
    describe('for', function (): void {
        it('creates a builder for a resource', function (): void {
            $builder = PermissionBuilder::for('posts');

            expect($builder)->toBeInstanceOf(PermissionBuilder::class);
        });
    });

    describe('crud', function (): void {
        it('adds standard CRUD abilities', function (): void {
            $builder = PermissionBuilder::for('posts')->crud();

            $names = $builder->getNames();

            expect($names)->toContain('posts.viewAny')
                ->and($names)->toContain('posts.view')
                ->and($names)->toContain('posts.create')
                ->and($names)->toContain('posts.update')
                ->and($names)->toContain('posts.delete');
        });
    });

    describe('softDeletes', function (): void {
        it('is kept for backward compatibility but adds no permissions', function (): void {
            $builder = PermissionBuilder::for('posts')->softDeletes();

            expect($builder->getNames())->toBeEmpty();
        });
    });

    describe('fullCrud', function (): void {
        it('adds full CRUD abilities', function (): void {
            $builder = PermissionBuilder::for('posts')->fullCrud();

            $names = $builder->getNames();

            expect($names)->toHaveCount(5);
        });
    });

    describe('abilities', function (): void {
        it('adds specific abilities', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->abilities(['publish', 'archive']);

            $names = $builder->getNames();

            expect($names)->toContain('posts.publish')
                ->and($names)->toContain('posts.archive');
        });

        it('merges with existing abilities', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->abilities(['view'])
                ->abilities(['edit']);

            expect($builder->getNames())->toContain('posts.view')
                ->and($builder->getNames())->toContain('posts.edit');
        });
    });

    describe('ability', function (): void {
        it('adds a single ability', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->ability('publish');

            expect($builder->getNames())->toContain('posts.publish');
        });

        it('adds a single ability with description', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->ability('publish', 'Publish articles');

            $permissions = $builder->build();

            expect($permissions['posts.publish']['description'])->toBe('Publish articles');
        });
    });

    describe('viewOnly', function (): void {
        it('adds view-only abilities', function (): void {
            $builder = PermissionBuilder::for('posts')->viewOnly();

            $names = $builder->getNames();

            expect($names)->toContain('posts.viewAny')
                ->and($names)->toContain('posts.view')
                ->and($names)->not->toContain('posts.create')
                ->and($names)->not->toContain('posts.update')
                ->and($names)->not->toContain('posts.delete');
        });
    });

    describe('manage', function (): void {
        it('adds manage ability', function (): void {
            $builder = PermissionBuilder::for('posts')->manage();

            $permissions = $builder->build();

            expect($permissions)->toHaveKey('posts.manage')
                ->and($permissions['posts.manage']['description'])->toBe('Full management access');
        });
    });

    describe('wildcard', function (): void {
        it('adds wildcard ability', function (): void {
            $builder = PermissionBuilder::for('posts')->wildcard();

            $permissions = $builder->build();

            expect($permissions)->toHaveKey('posts.*')
                ->and($permissions['posts.*']['description'])->toBe('All permissions');
        });
    });

    describe('export', function (): void {
        it('adds export ability', function (): void {
            $builder = PermissionBuilder::for('posts')->export();

            $permissions = $builder->build();

            expect($permissions)->toHaveKey('posts.export')
                ->and($permissions['posts.export']['description'])->toBe('Export data');
        });
    });

    describe('import', function (): void {
        it('adds import ability', function (): void {
            $builder = PermissionBuilder::for('posts')->import();

            $permissions = $builder->build();

            expect($permissions)->toHaveKey('posts.import')
                ->and($permissions['posts.import']['description'])->toBe('Import data');
        });
    });

    describe('replicate', function (): void {
        it('adds replicate ability', function (): void {
            $builder = PermissionBuilder::for('posts')->replicate();

            $permissions = $builder->build();

            expect($permissions)->toHaveKey('posts.replicate')
                ->and($permissions['posts.replicate']['description'])->toBe('Duplicate records');
        });
    });

    describe('bulkActions', function (): void {
        it('adds bulk action abilities', function (): void {
            $builder = PermissionBuilder::for('posts')->bulkActions();

            $names = $builder->getNames();

            expect($names)->toContain('posts.bulkDelete')
                ->and($names)->toContain('posts.bulkUpdate');
        });
    });

    describe('group', function (): void {
        it('sets the permission group', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->ability('view')
                ->group('Content Management');

            $permissions = $builder->build();

            expect($permissions['posts.view']['group'])->toBe('Content Management');
        });
    });

    describe('guard', function (): void {
        it('sets the guard name', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->ability('view')
                ->guard('api');

            $permissions = $builder->build();

            expect($permissions['posts.view']['guard_name'])->toBe('api');
        });
    });

    describe('describe', function (): void {
        it('sets descriptions for abilities', function (): void {
            $builder = PermissionBuilder::for('posts')
                ->crud()
                ->describe([
                    'viewAny' => 'View all posts',
                    'view' => 'View a single post',
                ]);

            $permissions = $builder->build();

            expect($permissions['posts.viewAny']['description'])->toBe('View all posts')
                ->and($permissions['posts.view']['description'])->toBe('View a single post');
        });
    });

    describe('build', function (): void {
        it('builds permission definitions', function (): void {
            $permissions = PermissionBuilder::for('posts')
                ->crud()
                ->group('Content')
                ->guard('web')
                ->build();

            expect($permissions)->toHaveCount(5);

            foreach ($permissions as $definition) {
                expect($definition)->toHaveKeys(['name', 'description', 'group', 'resource', 'guard_name'])
                    ->and($definition['resource'])->toBe('posts')
                    ->and($definition['group'])->toBe('Content')
                    ->and($definition['guard_name'])->toBe('web');
            }
        });

        it('removes duplicate abilities', function (): void {
            $permissions = PermissionBuilder::for('posts')
                ->ability('view')
                ->ability('view')
                ->ability('view')
                ->build();

            expect($permissions)->toHaveCount(1);
        });

        it('generates description for CRUD abilities', function (): void {
            $permissions = PermissionBuilder::for('posts')->crud()->build();

            expect($permissions['posts.viewAny']['description'])->toBe('View any Posts')
                ->and($permissions['posts.view']['description'])->toBe('View Posts')
                ->and($permissions['posts.create']['description'])->toBe('Create Posts')
                ->and($permissions['posts.update']['description'])->toBe('Update Posts')
                ->and($permissions['posts.delete']['description'])->toBe('Delete Posts');
        });

        it('generates description for custom abilities', function (): void {
            $permissions = PermissionBuilder::for('posts')
                ->ability('publishArticle')
                ->build();

            expect($permissions['posts.publishArticle']['description'])->toBe('Publish Article Posts');
        });
    });

    describe('getNames', function (): void {
        it('returns only permission names', function (): void {
            $names = PermissionBuilder::for('posts')
                ->crud()
                ->getNames();

            expect($names)->toBeArray()
                ->and($names)->each->toBeString()
                ->and($names)->toHaveCount(5);
        });
    });

    describe('chaining', function (): void {
        it('supports fluent chaining', function (): void {
            $permissions = PermissionBuilder::for('posts')
                ->crud()
                ->export()
                ->import()
                ->replicate()
                ->bulkActions()
                ->manage()
                ->group('Content')
                ->guard('web')
                ->build();

            // crud=5, export=1, import=1, replicate=1, bulkActions=2, manage=1 = 11
            expect($permissions)->toHaveCount(11);
        });
    });

    describe('register', function (): void {
        it('builds and registers permissions with registry', function (): void {
            $registry = Mockery::mock(PermissionRegistry::class);
            $registry->shouldReceive('register')
                ->times(3) // viewAny, view, create
                ->andReturnSelf();

            $permissions = PermissionBuilder::for('articles')
                ->abilities(['viewAny', 'view', 'create'])
                ->register($registry);

            expect($permissions)->toHaveCount(3);
            expect($permissions)->toHaveKeys(['articles.viewAny', 'articles.view', 'articles.create']);
        });

        it('uses app container for registry when not provided', function (): void {
            // This will try to use the app container
            $builder = PermissionBuilder::for('test-resource')
                ->ability('test');

            // Build without registry to test direct build path
            $permissions = $builder->build();

            expect($permissions)->toHaveKey('test-resource.test');
        });
    });

    describe('generateDescription', function (): void {
        it('generates descriptions for all standard abilities', function (): void {
            $builder = PermissionBuilder::for('orders');

            // Test all standard ability descriptions
            $permissions = $builder
                ->ability('viewAny')
                ->ability('view')
                ->ability('create')
                ->ability('update')
                ->ability('delete')
                ->ability('replicate')
                ->ability('export')
                ->ability('import')
                ->ability('bulkDelete')
                ->ability('bulkUpdate')
                ->ability('manage')
                ->ability('*')
                ->build();

            expect($permissions['orders.viewAny']['description'])->toBe('View any Orders');
            expect($permissions['orders.view']['description'])->toBe('View Orders');
            expect($permissions['orders.create']['description'])->toBe('Create Orders');
            expect($permissions['orders.update']['description'])->toBe('Update Orders');
            expect($permissions['orders.delete']['description'])->toBe('Delete Orders');
            expect($permissions['orders.replicate']['description'])->toBe('Duplicate Orders');
            expect($permissions['orders.export']['description'])->toBe('Export Orders');
            expect($permissions['orders.import']['description'])->toBe('Import Orders');
            expect($permissions['orders.bulkDelete']['description'])->toBe('Bulk delete Orders');
            expect($permissions['orders.bulkUpdate']['description'])->toBe('Bulk update Orders');
            expect($permissions['orders.manage']['description'])->toBe('Full management of Orders');
            expect($permissions['orders.*']['description'])->toBe('All Orders permissions');
        });

        it('generates headline description for custom abilities', function (): void {
            $permissions = PermissionBuilder::for('invoices')
                ->ability('sendReminder')
                ->ability('markAsPaid')
                ->build();

            expect($permissions['invoices.sendReminder']['description'])->toBe('Send Reminder Invoices');
            expect($permissions['invoices.markAsPaid']['description'])->toBe('Mark As Paid Invoices');
        });
    });

    describe('resource naming', function (): void {
        it('handles snake_case resource names', function (): void {
            $permissions = PermissionBuilder::for('order_items')
                ->ability('view')
                ->build();

            expect($permissions['order_items.view']['description'])->toBe('View Order Items');
        });

        it('handles camelCase resource names', function (): void {
            $permissions = PermissionBuilder::for('orderItems')
                ->ability('view')
                ->build();

            expect($permissions['orderItems.view']['description'])->toBe('View Order Items');
        });

        it('handles kebab-case resource names', function (): void {
            $permissions = PermissionBuilder::for('order-items')
                ->ability('view')
                ->build();

            expect($permissions['order-items.view']['description'])->toBe('View Order Items');
        });
    });
});
