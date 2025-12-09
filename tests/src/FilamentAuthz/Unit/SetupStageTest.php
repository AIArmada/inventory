<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\SetupStage;

test('setup stage enum has labels', function (): void {
    foreach (SetupStage::cases() as $stage) {
        expect($stage->label())->toBeString()->not->toBeEmpty();
    }
});

test('setup stage enum has icons', function (): void {
    foreach (SetupStage::cases() as $stage) {
        expect($stage->icon())->toBeString()->not->toBeEmpty();
    }
});

test('setup stage has welcome stage', function (): void {
    expect(SetupStage::Welcome->value)->toBe('welcome')
        ->and(SetupStage::Welcome->label())->toBe('Welcome');
});

test('setup stage has complete stage', function (): void {
    expect(SetupStage::Complete->value)->toBe('complete')
        ->and(SetupStage::Complete->label())->toBe('Complete');
});

test('setup stage icons are non-empty', function (): void {
    expect(SetupStage::Welcome->icon())->toBe('👋')
        ->and(SetupStage::Detection->icon())->toBe('🔍')
        ->and(SetupStage::Complete->icon())->toBe('✅');
});
