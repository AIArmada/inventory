<?php

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentDocs\Resources\DocResource;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create([
        'name' => 'Sarah Chen',
        'email' => 'admin@commerce.demo',
    ]);
});

test('docs navigation badges are owner-scoped (single tenancy)', function () {
    OwnerContext::withOwner($this->owner, function (): void {
        Doc::create([
            'doc_number' => 'DOC-1',
            'doc_type' => 'invoice',
            'status' => DocStatus::PENDING,
            'issue_date' => now(),
            'subtotal' => '100.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => '100.00',
            'currency' => 'MYR',
            'owner_type' => $this->owner->getMorphClass(),
            'owner_id' => $this->owner->id,
        ]);

        Doc::create([
            'doc_number' => 'DOC-2',
            'doc_type' => 'invoice',
            'status' => DocStatus::OVERDUE,
            'issue_date' => now(),
            'subtotal' => '200.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => '200.00',
            'currency' => 'MYR',
            'owner_type' => $this->owner->getMorphClass(),
            'owner_id' => $this->owner->id,
        ]);
    });

    OwnerContext::withOwner($this->owner, function (): void {
        expect(DocResource::getNavigationBadge())->toBe('2');
    });
});
