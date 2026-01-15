<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\Models\DocTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doc uses uuid as primary key', function (): void {
    $doc = Doc::factory()->create();

    expect($doc->id)->toBeString()
        ->and(mb_strlen($doc->id))->toBe(36);
});

test('doc has correct table name from config', function (): void {
    $doc = new Doc;

    expect($doc->getTable())->toBe(config('docs.database.tables.docs', 'docs'));
});

test('doc belongs to template', function (): void {
    $template = DocTemplate::factory()->create();
    $doc = Doc::factory()->create(['doc_template_id' => $template->id]);

    expect($doc->template)->toBeInstanceOf(DocTemplate::class)
        ->and($doc->template->id)->toBe($template->id);
});

test('doc has many status histories relation', function (): void {
    $doc = Doc::factory()->create();

    expect($doc->statusHistories())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('doc has many payments relation', function (): void {
    $doc = Doc::factory()->create();

    expect($doc->payments())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('doc has many versions relation', function (): void {
    $doc = Doc::factory()->create();

    expect($doc->versions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('doc has many emails relation', function (): void {
    $doc = Doc::factory()->create();

    expect($doc->emails())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('doc has many approvals', function (): void {
    $doc = Doc::factory()->create();
    DocApproval::factory()->count(2)->create(['doc_id' => $doc->id]);

    expect($doc->approvals)->toHaveCount(2);
});

test('doc is overdue when past due date and not paid', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'due_date' => CarbonImmutable::now()->subDays(5),
    ]);

    expect($doc->isOverdue())->toBeTrue();
});

test('doc is not overdue when status is paid', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::PAID,
        'due_date' => CarbonImmutable::now()->subDays(5),
    ]);

    expect($doc->isOverdue())->toBeFalse();
});

test('doc is not overdue when status is cancelled', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::CANCELLED,
        'due_date' => CarbonImmutable::now()->subDays(5),
    ]);

    expect($doc->isOverdue())->toBeFalse();
});

test('doc is not overdue when due date is in future', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'due_date' => CarbonImmutable::now()->addDays(5),
    ]);

    expect($doc->isOverdue())->toBeFalse();
});

test('doc is not overdue when due date is null', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'due_date' => null,
    ]);

    expect($doc->isOverdue())->toBeFalse();
});

test('doc is paid returns true when status is paid', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::PAID]);

    expect($doc->isPaid())->toBeTrue();
});

test('doc is paid returns false when status is not paid', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::SENT]);

    expect($doc->isPaid())->toBeFalse();
});

test('doc can be paid returns true for payable statuses', function (): void {
    $pendingDoc = Doc::factory()->create(['status' => DocStatus::PENDING]);
    $sentDoc = Doc::factory()->create(['status' => DocStatus::SENT]);
    $overdueDoc = Doc::factory()->create(['status' => DocStatus::OVERDUE]);
    $partialDoc = Doc::factory()->create(['status' => DocStatus::PARTIALLY_PAID]);

    expect($pendingDoc->canBePaid())->toBeTrue()
        ->and($sentDoc->canBePaid())->toBeTrue()
        ->and($overdueDoc->canBePaid())->toBeTrue()
        ->and($partialDoc->canBePaid())->toBeTrue();
});

test('doc can be paid returns false for non-payable statuses', function (): void {
    $draftDoc = Doc::factory()->create(['status' => DocStatus::DRAFT]);
    $paidDoc = Doc::factory()->create(['status' => DocStatus::PAID]);
    $cancelledDoc = Doc::factory()->create(['status' => DocStatus::CANCELLED]);

    expect($draftDoc->canBePaid())->toBeFalse()
        ->and($paidDoc->canBePaid())->toBeFalse()
        ->and($cancelledDoc->canBePaid())->toBeFalse();
});

test('mark as paid updates status and paid_at', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    $doc = Doc::factory()->create(['status' => DocStatus::SENT]);
    $doc->markAsPaid();

    expect($doc->fresh()->status)->toBe(DocStatus::PAID)
        ->and($doc->fresh()->paid_at)->not->toBeNull();

    CarbonImmutable::setTestNow();
});

test('mark as paid creates status history', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::SENT]);
    $doc->markAsPaid('Payment received via bank transfer');

    expect($doc->statusHistories)->toHaveCount(1)
        ->and($doc->statusHistories->first()->status)->toBe(DocStatus::PAID)
        ->and($doc->statusHistories->first()->notes)->toBe('Payment received via bank transfer');
});

test('mark as sent updates status from draft', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::DRAFT]);
    $doc->markAsSent();

    expect($doc->fresh()->status)->toBe(DocStatus::SENT);
});

test('mark as sent updates status from pending', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::PENDING]);
    $doc->markAsSent();

    expect($doc->fresh()->status)->toBe(DocStatus::SENT);
});

test('mark as sent does not update status if already sent', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::SENT]);
    $initialHistoryCount = $doc->statusHistories()->count();

    $doc->markAsSent();

    expect($doc->fresh()->status)->toBe(DocStatus::SENT)
        ->and($doc->statusHistories()->count())->toBe($initialHistoryCount);
});

test('mark as sent creates status history', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::DRAFT]);
    $doc->markAsSent('Sent to customer');

    expect($doc->statusHistories)->toHaveCount(1)
        ->and($doc->statusHistories->first()->status)->toBe(DocStatus::SENT)
        ->and($doc->statusHistories->first()->notes)->toBe('Sent to customer');
});

test('cancel updates status to cancelled', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::SENT]);
    $doc->cancel();

    expect($doc->fresh()->status)->toBe(DocStatus::CANCELLED);
});

test('cancel does not work on paid docs', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::PAID]);
    $doc->cancel();

    expect($doc->fresh()->status)->toBe(DocStatus::PAID);
});

test('cancel creates status history', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::SENT]);
    $doc->cancel('Customer requested cancellation');

    expect($doc->statusHistories)->toHaveCount(1)
        ->and($doc->statusHistories->first()->status)->toBe(DocStatus::CANCELLED)
        ->and($doc->statusHistories->first()->notes)->toBe('Customer requested cancellation');
});

test('update status marks overdue docs as overdue', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'due_date' => CarbonImmutable::now()->subDays(5),
    ]);

    $doc->updateStatus();

    expect($doc->fresh()->status)->toBe(DocStatus::OVERDUE);
});

test('update status does not change non-overdue docs', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'due_date' => CarbonImmutable::now()->addDays(5),
    ]);

    $doc->updateStatus();

    expect($doc->fresh()->status)->toBe(DocStatus::SENT);
});

test('update status creates history when marking as overdue', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'due_date' => CarbonImmutable::now()->subDays(5),
    ]);

    $doc->updateStatus();

    expect($doc->statusHistories)->toHaveCount(1)
        ->and($doc->statusHistories->first()->status)->toBe(DocStatus::OVERDUE);
});

test('deleting doc cascades to related approvals', function (): void {
    $doc = Doc::factory()->create();
    DocApproval::factory()->create(['doc_id' => $doc->id]);

    $docId = $doc->id;
    $doc->delete();

    expect(DocApproval::where('doc_id', $docId)->count())->toBe(0);
});

test('doc casts status to enum', function (): void {
    $doc = Doc::factory()->create(['status' => DocStatus::DRAFT]);

    expect($doc->status)->toBeInstanceOf(DocStatus::class)
        ->and($doc->status)->toBe(DocStatus::DRAFT);
});

test('doc casts items to array', function (): void {
    $items = [
        ['name' => 'Item 1', 'quantity' => 2, 'price' => 100],
        ['name' => 'Item 2', 'quantity' => 1, 'price' => 50],
    ];

    $doc = Doc::factory()->create(['items' => $items]);

    expect($doc->items)->toBeArray()
        ->and($doc->items)->toHaveCount(2)
        ->and($doc->items[0]['name'])->toBe('Item 1');
});

test('doc casts customer_data to array', function (): void {
    $customerData = ['name' => 'John Doe', 'email' => 'john@example.com'];
    $doc = Doc::factory()->create(['customer_data' => $customerData]);

    expect($doc->customer_data)->toBeArray()
        ->and($doc->customer_data['name'])->toBe('John Doe');
});

test('doc casts metadata to array', function (): void {
    $metadata = ['source' => 'api', 'version' => '1.0'];
    $doc = Doc::factory()->create(['metadata' => $metadata]);

    expect($doc->metadata)->toBeArray()
        ->and($doc->metadata['source'])->toBe('api');
});
