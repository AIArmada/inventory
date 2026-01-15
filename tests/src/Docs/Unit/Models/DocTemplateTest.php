<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('doc template relationships', function (): void {
    $template = DocTemplate::factory()->create();
    $doc = Doc::factory()->create(['doc_template_id' => $template->id]);

    expect($template->docs)->toHaveCount(1)
        ->and($template->docs->first()->id)->toBe($doc->id);
});

test('doc template set as default logic', function (): void {
    // Create existing default
    $default = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
    ]);

    // Create new template
    $new = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => false,
    ]);

    // Set new as default
    $new->setAsDefault();

    expect($new->fresh()->is_default)->toBeTrue();
    expect($default->fresh()->is_default)->toBeFalse();
});

test('doc template set as default respects owner', function (): void {
    config(['docs.owner.enabled' => true]);
    $migration = require __DIR__ . '/../../../../../packages/docs/database/migrations/2000_06_01_000003_add_owner_columns_to_docs_tables.php';
    $migration->up();

    // Global default
    $global = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
        'owner_type' => null,
    ]);

    // Owned default (simulate owner)
    $ownerType = 'App\\Models\\User';
    $ownerId = (string) Str::uuid();

    $ownedDefault = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
    ]);

    $ownedNew = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => false,
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
    ]);

    $ownedNew->setAsDefault();

    expect($ownedNew->fresh()->is_default)->toBeTrue();
    expect($ownedDefault->fresh()->is_default)->toBeFalse();
    // Global should stay default for global context
    expect($global->fresh()->is_default)->toBeTrue();
});

test('doc template deleting logic', function (): void {
    $template = DocTemplate::factory()->create();
    $doc = Doc::factory()->create(['doc_template_id' => $template->id]);

    $template->delete();

    expect($doc->fresh()->doc_template_id)->toBeNull();
});

test('doc template default scope', function (): void {
    $default = DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
    ]);
    DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => false,
    ]);

    $found = DocTemplate::query()->default('invoice')->first();
    expect($found->id)->toBe($default->id);
});
