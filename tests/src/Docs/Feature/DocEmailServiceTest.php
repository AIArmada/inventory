<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\Docs\Services\DocEmailService;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Doc::query()->delete();
    DocEmail::query()->delete();
    DocEmailTemplate::query()->delete();

    // Disable email queueing for tests
    config()->set('docs.email.queue_enabled', false);
    config()->set('docs.email.attach_pdf', false);

    // Register tracking routes for tests
    Route::get('/docs/track/open/{token}', fn () => response()->noContent())->name('docs.track.open');
    Route::get('/docs/track/click/{token}', fn () => response()->noContent())->name('docs.track.click');
});

test('it can send document email', function (): void {
    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
        'doc_number' => 'INV-2024-001',
        'status' => DocStatus::PENDING,
        'total' => 1000.00,
        'currency' => 'MYR',
    ]);

    $email = $emailService->send(
        doc: $doc,
        recipientEmail: 'customer@example.com',
        recipientName: 'John Doe',
    );

    expect($email)
        ->toBeInstanceOf(DocEmail::class)
        ->and($email->recipient_email)->toBe('customer@example.com')
        ->and($email->recipient_name)->toBe('John Doe')
        ->and($email->status)->toBe(EmailStatus::Sent);
});

test('it uses template when available', function (): void {
    DocEmailTemplate::create([
        'name' => 'Invoice Template',
        'slug' => 'invoice-template',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'send',
        'subject' => 'Invoice #{{ doc_number }}',
        'body' => 'Dear {{ customer_name }}, please find invoice {{ doc_number }}.',
        'is_active' => true,
    ]);

    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
        'doc_number' => 'INV-2024-002',
        'customer_data' => ['name' => 'Jane Doe'],
    ]);

    $email = $emailService->send(
        doc: $doc,
        recipientEmail: 'jane@example.com',
    );

    expect($email->subject)->toContain('INV-2024-002');
});

test('it can send reminder for overdue document', function (): void {
    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
        'doc_number' => 'INV-2024-003',
        'status' => DocStatus::OVERDUE,
        'due_date' => now()->subDays(5),
    ]);

    $email = $emailService->sendReminder(
        doc: $doc,
        recipientEmail: 'overdue@example.com',
    );

    expect($email)
        ->toBeInstanceOf(DocEmail::class)
        ->and($email->recipient_email)->toBe('overdue@example.com');
});

test('it finds correct template for doc type and trigger', function (): void {
    DocEmailTemplate::create([
        'name' => 'Invoice Send',
        'slug' => 'invoice-send',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'send',
        'subject' => 'Invoice',
        'body' => 'Body',
        'is_active' => true,
    ]);

    DocEmailTemplate::create([
        'name' => 'Invoice Reminder',
        'slug' => 'invoice-reminder',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'reminder',
        'subject' => 'Reminder',
        'body' => 'Body',
        'is_active' => true,
    ]);

    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
    ]);

    $sendTemplate = $emailService->findTemplate($doc, 'send');
    $reminderTemplate = $emailService->findTemplate($doc, 'reminder');

    expect($sendTemplate->name)->toBe('Invoice Send')
        ->and($reminderTemplate->name)->toBe('Invoice Reminder');
});

test('it returns null for non-existent template', function (): void {
    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => 'non_existent',
    ]);

    $template = $emailService->findTemplate($doc, 'send');

    expect($template)->toBeNull();
});

test('it ignores inactive templates', function (): void {
    DocEmailTemplate::create([
        'name' => 'Inactive Template',
        'slug' => 'inactive-template',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'send',
        'subject' => 'Subject',
        'body' => 'Body',
        'is_active' => false,
    ]);

    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
    ]);

    $template = $emailService->findTemplate($doc, 'send');

    expect($template)->toBeNull();
});

test('it generates tracking pixel url', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'track@example.com',
        'subject' => 'Test',
        'body' => 'Test body',
        'status' => EmailStatus::Sent,
    ]);

    $emailService = app(DocEmailService::class);
    $url = $emailService->getTrackingPixelUrl($email);

    expect($url)->toBeString()->toContain('docs/track/open');
});

test('it generates tracked link url', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'track@example.com',
        'subject' => 'Test',
        'body' => 'Test body',
        'status' => EmailStatus::Sent,
    ]);

    $emailService = app(DocEmailService::class);
    $originalUrl = 'https://example.com/document';
    $trackedUrl = $emailService->getTrackedLinkUrl($email, $originalUrl);

    expect($trackedUrl)->toBeString()->toContain('docs/track/click');
});

test('doc can have multiple emails', function (): void {
    $doc = Doc::factory()->create();

    DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'first@example.com',
        'subject' => 'First',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'second@example.com',
        'subject' => 'Second',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $doc->refresh();

    expect($doc->emails)->toHaveCount(2);
});

test('email tracks sent timestamp', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'test@example.com',
        'subject' => 'Test',
        'body' => 'Body',
        'status' => EmailStatus::Queued,
    ]);

    expect($email->sent_at)->toBeNull();

    $email->update([
        'status' => EmailStatus::Sent,
        'sent_at' => now(),
    ]);

    expect($email->sent_at)->not->toBeNull();
});

test('email tracks opened timestamp', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'test@example.com',
        'subject' => 'Test',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
        'sent_at' => now(),
    ]);

    expect($email->opened_at)->toBeNull();

    $email->markAsOpened();

    expect($email->opened_at)->not->toBeNull();
});
