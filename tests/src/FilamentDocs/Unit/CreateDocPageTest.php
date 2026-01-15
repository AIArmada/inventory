<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\CreateDoc;

uses(TestCase::class);

it('delegates document creation to DocService and defaults generate_pdf from config', function (): void {
    // Disable PDF generation to avoid view loading issues in tests
    config()->set('filament-docs.features.auto_generate_pdf', false);
    config()->set('docs.owner.enabled', false);

    DocTemplate::factory()->create([
        'doc_type' => 'invoice',
        'is_default' => true,
    ]);

    $page = new CreateDoc;

    $method = new ReflectionMethod(CreateDoc::class, 'handleRecordCreation');
    $method->setAccessible(true);

    $doc = $method->invoke($page, [
        'doc_type' => 'invoice',
        'items' => [
            ['name' => 'Test Item', 'quantity' => 1, 'price' => 100],
        ],
        'customer_data' => ['name' => 'Test Customer'],
    ]);

    expect($doc)->toBeInstanceOf(Doc::class)
        ->and($doc->doc_type)->toBe('invoice')
        ->and($doc->exists)->toBeTrue();
});

it('uses config value for auto_generate_pdf when not explicitly set', function (): void {
    config()->set('filament-docs.features.auto_generate_pdf', false);
    config()->set('docs.owner.enabled', false);

    DocTemplate::factory()->create([
        'doc_type' => 'receipt',
        'is_default' => true,
    ]);

    $page = new CreateDoc;

    $method = new ReflectionMethod(CreateDoc::class, 'handleRecordCreation');
    $method->setAccessible(true);

    $doc = $method->invoke($page, [
        'doc_type' => 'receipt',
        'items' => [
            ['name' => 'Test Item', 'quantity' => 1, 'price' => 50],
        ],
        'customer_data' => ['name' => 'Another Customer'],
    ]);

    expect($doc)->toBeInstanceOf(Doc::class)
        ->and($doc->pdf_path)->toBeNull();
});
