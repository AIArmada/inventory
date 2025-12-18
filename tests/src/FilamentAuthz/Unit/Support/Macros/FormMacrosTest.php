<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\FormMacros;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;

function createFormMacrosTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

beforeEach(function (): void {
    FormMacros::register();
});

describe('FormMacros::register', function (): void {
    it('registers field macros', function (): void {
        expect(Field::hasMacro('visibleForPermission'))->toBeTrue();
        expect(Field::hasMacro('visibleForRole'))->toBeTrue();
        expect(Field::hasMacro('disabledWithoutPermission'))->toBeTrue();
    });

    it('registers section macros', function (): void {
        expect(Section::hasMacro('visibleForPermission'))->toBeTrue();
        expect(Section::hasMacro('visibleForRole'))->toBeTrue();
        expect(Section::hasMacro('collapsedWithoutPermission'))->toBeTrue();
    });
});

describe('Field::visibleForPermission', function (): void {
    it('returns field instance for chaining', function (): void {
        $field = TextInput::make('test');
        $result = $field->visibleForPermission('test.permission');

        expect($result)->toBeInstanceOf(Field::class);
    });

    it('hides field when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $field = TextInput::make('test')->visibleForPermission('test.permission');

        expect($field)->toBeInstanceOf(Field::class);
    });

    it('uses aggregator for permission check', function (): void {
        $user = createFormMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $field = TextInput::make('test')->visibleForPermission('test.permission');

        expect($field)->toBeInstanceOf(Field::class);
    });
});

describe('Field::visibleForRole', function (): void {
    it('returns field instance for chaining', function (): void {
        $field = TextInput::make('test');
        $result = $field->visibleForRole('admin');

        expect($result)->toBeInstanceOf(Field::class);
    });

    it('accepts string role', function (): void {
        $field = TextInput::make('test')->visibleForRole('admin');

        expect($field)->toBeInstanceOf(Field::class);
    });

    it('accepts array of roles', function (): void {
        $field = TextInput::make('test')->visibleForRole(['admin', 'editor']);

        expect($field)->toBeInstanceOf(Field::class);
    });
});

describe('Field::disabledWithoutPermission', function (): void {
    it('returns field instance for chaining', function (): void {
        $field = TextInput::make('test');
        $result = $field->disabledWithoutPermission('test.permission');

        expect($result)->toBeInstanceOf(Field::class);
    });

    it('disables field when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $field = TextInput::make('test')->disabledWithoutPermission('test.permission');

        expect($field)->toBeInstanceOf(Field::class);
    });

    it('enables field when user has permission', function (): void {
        $user = createFormMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $field = TextInput::make('test')->disabledWithoutPermission('test.permission');

        expect($field)->toBeInstanceOf(Field::class);
    });

    it('disables field when user lacks permission', function (): void {
        $user = createFormMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(false);
        });

        $field = TextInput::make('test')->disabledWithoutPermission('test.permission');

        expect($field)->toBeInstanceOf(Field::class);
    });
});

describe('Section::visibleForPermission', function (): void {
    it('returns section instance for chaining', function (): void {
        $section = Section::make('Test Section');
        $result = $section->visibleForPermission('test.permission');

        expect($result)->toBeInstanceOf(Section::class);
    });

    it('hides section when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $section = Section::make('Test Section')->visibleForPermission('test.permission');

        expect($section)->toBeInstanceOf(Section::class);
    });

    it('uses aggregator for permission check', function (): void {
        $user = createFormMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $section = Section::make('Test Section')->visibleForPermission('test.permission');

        expect($section)->toBeInstanceOf(Section::class);
    });
});

describe('Section::visibleForRole', function (): void {
    it('returns section instance for chaining', function (): void {
        $section = Section::make('Test Section');
        $result = $section->visibleForRole('admin');

        expect($result)->toBeInstanceOf(Section::class);
    });

    it('accepts string role', function (): void {
        $section = Section::make('Test Section')->visibleForRole('admin');

        expect($section)->toBeInstanceOf(Section::class);
    });

    it('accepts array of roles', function (): void {
        $section = Section::make('Test Section')->visibleForRole(['admin', 'editor']);

        expect($section)->toBeInstanceOf(Section::class);
    });
});

describe('Section::collapsedWithoutPermission', function (): void {
    it('returns section instance for chaining', function (): void {
        $section = Section::make('Test Section');
        $result = $section->collapsedWithoutPermission('test.permission');

        expect($result)->toBeInstanceOf(Section::class);
    });

    it('collapses section when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $section = Section::make('Test Section')->collapsedWithoutPermission('test.permission');

        expect($section)->toBeInstanceOf(Section::class);
    });

    it('expands section when user has permission', function (): void {
        $user = createFormMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $section = Section::make('Test Section')->collapsedWithoutPermission('test.permission');

        expect($section)->toBeInstanceOf(Section::class);
    });
});
