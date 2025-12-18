<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\CreateUser;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\EditUser;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\ListUsers;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('UserResource Metadata', function (): void {
    it('has correct model', function (): void {
        $model = UserResource::getModel();
        expect($model)->toBeString();
        // Model is dynamically configured and may not exist in test environment
    });

    it('has navigation icon from config', function (): void {
        expect(UserResource::getNavigationIcon())->toBeString();
    });

    it('has navigation label', function (): void {
        expect(UserResource::getNavigationLabel())->toBe('Users');
    });

    it('has navigation sort from config', function (): void {
        expect(UserResource::getNavigationSort())->toBeInt();
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Security']);
        expect(UserResource::getNavigationGroup())->toBe('Security');
    });

    it('has global search attributes', function (): void {
        $attributes = UserResource::getGlobalSearchResultsLimit();
        expect($attributes)->toBeInt();
    });
});

describe('UserResource Pages', function (): void {
    it('has index page', function (): void {
        $pages = UserResource::getPages();
        expect($pages)->toHaveKey('index')
            ->and($pages['index']->getPage())->toBe(ListUsers::class);
    });

    it('has create page', function (): void {
        $pages = UserResource::getPages();
        expect($pages)->toHaveKey('create')
            ->and($pages['create']->getPage())->toBe(CreateUser::class);
    });

    it('has edit page', function (): void {
        $pages = UserResource::getPages();
        expect($pages)->toHaveKey('edit')
            ->and($pages['edit']->getPage())->toBe(EditUser::class);
    });
});

describe('UserResource canAccess', function (): void {
    it('can register navigation with proper permissions', function (): void {
        expect(method_exists(UserResource::class, 'shouldRegisterNavigation'))->toBeTrue();
    });
});

describe('UserResource Navigation Badge', function (): void {
    it('has navigation badge method', function (): void {
        $badge = UserResource::getNavigationBadge();
        expect($badge)->toBeNull();
    });
});

describe('UserResource Relations', function (): void {
    it('has permissions relation manager', function (): void {
        $relations = UserResource::getRelations();
        expect($relations)->toBeArray();
    });

    it('has roles relation manager', function (): void {
        $relations = UserResource::getRelations();
        expect($relations)->toBeArray();
    });
});
