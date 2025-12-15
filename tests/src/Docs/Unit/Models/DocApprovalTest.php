<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doc approval status helpers', function (): void {
    $approval = DocApproval::factory()->create(['status' => 'pending']);
    expect($approval->isPending())->toBeTrue();
    expect($approval->isApproved())->toBeFalse();
    expect($approval->isRejected())->toBeFalse();

    $approval->approve('Looks good');
    expect($approval->isPending())->toBeFalse();
    expect($approval->isApproved())->toBeTrue();
    expect($approval->comments)->toBe('Looks good');
    expect($approval->approved_at)->not->toBeNull();

    $rejected = DocApproval::factory()->create(['status' => 'pending']);
    $rejected->reject('Bad');
    expect($rejected->isRejected())->toBeTrue();
    expect($rejected->comments)->toBe('Bad');
    expect($rejected->rejected_at)->not->toBeNull();
});

test('doc approval expiry', function (): void {
    $approval = DocApproval::factory()->create([
        'expires_at' => now()->subDay(),
    ]);
    expect($approval->isExpired())->toBeTrue();

    $valid = DocApproval::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    expect($valid->isExpired())->toBeFalse();
});

test('doc approval relationships', function (): void {
    $doc = Doc::factory()->create();
    $approval = DocApproval::factory()->create(['doc_id' => $doc->id]);

    expect($approval->doc->id)->toBe($doc->id);
});
