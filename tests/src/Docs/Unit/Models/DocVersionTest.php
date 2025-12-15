<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doc version relationships', function (): void {
    $doc = Doc::factory()->create();
    $version = DocVersion::factory()->create(['doc_id' => $doc->id]);

    expect($version->doc->id)->toBe($doc->id);
});

test('doc version restore', function (): void {
    $doc = Doc::factory()->create(['notes' => 'Current Notes']);
    $snapshot = $doc->toArray();
    $snapshot['notes'] = 'Old Notes';

    $version = DocVersion::factory()->create([
        'doc_id' => $doc->id,
        'snapshot' => $snapshot,
    ]);

    $version->restore();

    expect($doc->fresh()->notes)->toBe('Old Notes');
});

test('doc version diff', function (): void {
    $v1 = DocVersion::factory()->create(['snapshot' => ['field' => 'Old Value', 'static' => 'Same']]);
    $v2 = DocVersion::factory()->create(['snapshot' => ['field' => 'New Value', 'static' => 'Same']]);

    // Diff v2 against v1 (comparing new to old)
    $diff = $v2->diff($v1);

    expect($diff)->toHaveKey('field')
        ->and($diff['field']['old'])->toBe('Old Value')
        ->and($diff['field']['new'])->toBe('New Value');

    expect($diff)->not->toHaveKey('static');
});
