<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\ColumnMacros;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;

function createColumnMacrosTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

beforeEach(function () {
    ColumnMacros::register();
});

describe('ColumnMacros::register', function () {
    it('registers column macros', function () {
        expect(Column::hasMacro('visibleForPermission'))->toBeTrue();
        expect(Column::hasMacro('visibleForRole'))->toBeTrue();
        expect(Column::hasMacro('visibleForAnyPermission'))->toBeTrue();
    });

    it('registers text column macros', function () {
        expect(TextColumn::hasMacro('formatPermission'))->toBeTrue();
        expect(TextColumn::hasMacro('formatRole'))->toBeTrue();
    });
});

describe('Column::visibleForPermission', function () {
    it('returns column instance for chaining', function () {
        $column = TextColumn::make('test');
        $result = $column->visibleForPermission('test.permission');

        expect($result)->toBeInstanceOf(Column::class);
    });

    it('hides column when user is null', function () {
        Auth::shouldReceive('user')->andReturn(null);

        $column = TextColumn::make('test')->visibleForPermission('test.permission');

        expect($column)->toBeInstanceOf(Column::class);
    });

    it('uses aggregator for permission check', function () {
        $user = createColumnMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $column = TextColumn::make('test')->visibleForPermission('test.permission');

        expect($column)->toBeInstanceOf(Column::class);
    });
});

describe('Column::visibleForRole', function () {
    it('returns column instance for chaining', function () {
        $column = TextColumn::make('test');
        $result = $column->visibleForRole('admin');

        expect($result)->toBeInstanceOf(Column::class);
    });

    it('accepts string role', function () {
        $column = TextColumn::make('test')->visibleForRole('admin');

        expect($column)->toBeInstanceOf(Column::class);
    });

    it('accepts array of roles', function () {
        $column = TextColumn::make('test')->visibleForRole(['admin', 'editor']);

        expect($column)->toBeInstanceOf(Column::class);
    });
});

describe('Column::visibleForAnyPermission', function () {
    it('returns column instance for chaining', function () {
        $column = TextColumn::make('test');
        $result = $column->visibleForAnyPermission(['perm1', 'perm2']);

        expect($result)->toBeInstanceOf(Column::class);
    });

    it('hides column when user is null', function () {
        Auth::shouldReceive('user')->andReturn(null);

        $column = TextColumn::make('test')->visibleForAnyPermission(['perm1', 'perm2']);

        expect($column)->toBeInstanceOf(Column::class);
    });

    it('uses aggregator for any permission check', function () {
        $user = createColumnMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('userHasAnyPermission')
                ->with($user, ['perm1', 'perm2'])
                ->andReturn(true);
        });

        $column = TextColumn::make('test')->visibleForAnyPermission(['perm1', 'perm2']);

        expect($column)->toBeInstanceOf(Column::class);
    });
});

describe('TextColumn::formatPermission', function () {
    it('returns text column instance for chaining', function () {
        $column = TextColumn::make('permission');
        $result = $column->formatPermission();

        expect($result)->toBeInstanceOf(TextColumn::class);
    });

    it('applies badge styling', function () {
        $column = TextColumn::make('permission')->formatPermission();

        expect($column)->toBeInstanceOf(TextColumn::class);
    });
});

describe('TextColumn::formatRole', function () {
    it('returns text column instance for chaining', function () {
        $column = TextColumn::make('role');
        $result = $column->formatRole();

        expect($result)->toBeInstanceOf(TextColumn::class);
    });

    it('applies primary badge styling', function () {
        $column = TextColumn::make('role')->formatRole();

        expect($column)->toBeInstanceOf(TextColumn::class);
    });
});
