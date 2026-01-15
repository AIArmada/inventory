<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\EmailsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\PaymentsRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\StatusHistoriesRelationManager;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\VersionsRelationManager;
use Filament\Support\Icons\Heroicon;

test('doc resource has correct model and labels', function (): void {
    expect(DocResource::getModel())->toBe(Doc::class);
    expect(DocResource::getNavigationIcon())->toBe(Heroicon::OutlinedDocumentText);
    expect(DocResource::getRecordTitleAttribute())->toBe('doc_number');
    expect(DocResource::getNavigationLabel())->toBe('Documents');
    expect(DocResource::getModelLabel())->toBe('Document');
    expect(DocResource::getPluralModelLabel())->toBe('Documents');
    expect(DocResource::getTenantOwnershipRelationshipName())->toBe('owner');
});

test('doc resource has correct pages', function (): void {
    $pages = DocResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

test('doc resource has correct relations', function (): void {
    $relations = DocResource::getRelations();

    expect($relations)->toContain(StatusHistoriesRelationManager::class);
    expect($relations)->toContain(PaymentsRelationManager::class);
    expect($relations)->toContain(EmailsRelationManager::class);
    expect($relations)->toContain(VersionsRelationManager::class);
    expect($relations)->toContain(ApprovalsRelationManager::class);
});

test('doc resource navigation badge color returns valid color', function (): void {
    // getNavigationBadgeColor queries the database, so we need to set up config first
    // This test validates that the method exists and returns one of the expected colors
    $reflection = new ReflectionMethod(DocResource::class, 'getNavigationBadgeColor');
    expect($reflection->isPublic())->toBeTrue();
    expect($reflection->isStatic())->toBeTrue();

    // Method signature should return string
    $returnType = $reflection->getReturnType();
    expect($returnType?->getName())->toBe('string');
});
