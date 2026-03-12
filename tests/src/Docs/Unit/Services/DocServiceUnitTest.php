<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Numbering\NumberStrategyRegistry;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\Services\SequenceManager;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Sent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('docs.storage.disk', 'docs');
    $this->numberRegistry = new NumberStrategyRegistry;
    $this->sequenceManager = new SequenceManager;
    $this->service = new DocService($this->numberRegistry, $this->sequenceManager);
});

test('generatePdf creates and stores pdf', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-001',
    ]);

    // Mock PDF Facade
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('view')
        ->once()
        ->withArgs(function ($view, $data) use ($doc) {
            return $data['doc']->id === $doc->id;
        })
        ->andReturn($pdfBuilderMock);

    $path = $this->service->generatePdf($doc);

    expect($path)->toBe('docs/INV-001.pdf');
    Storage::disk('docs')->assertExists('docs/INV-001.pdf');
    expect($doc->fresh()->pdf_path)->toBe('docs/INV-001.pdf');
});

test('generatePdf sanitizes doc_number to prevent path traversal', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV/../../evil',
    ]);

    // Mock PDF Facade
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('view')->andReturn($pdfBuilderMock);

    $path = $this->service->generatePdf($doc);

    expect($path)->toBe('docs/INV-evil.pdf');
    Storage::disk('docs')->assertExists('docs/INV-evil.pdf');
    expect($doc->fresh()->pdf_path)->toBe('docs/INV-evil.pdf');
});

test('downloadPdf returns existing path if exists', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-001',
        'pdf_path' => 'docs/INV-001.pdf',
    ]);

    Storage::disk('docs')->put('docs/INV-001.pdf', 'dummy content');

    // Should NOT call generatePdf (Pdf facade)
    Pdf::shouldReceive('view')->never();

    $path = $this->service->downloadPdf($doc);
    expect($path)->toBe('docs/INV-001.pdf');
});

test('downloadPdf generates pdf if missing', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-002',
        // pdf_path might be set but file missing
        'pdf_path' => 'docs/INV-002.pdf',
    ]);

    // File content missing in storage

    // Mock PDF Facade
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('view')->andReturn($pdfBuilderMock);

    $path = $this->service->downloadPdf($doc);
    expect($path)->toBe('docs/INV-002.pdf');
});

test('markAsSent marks doc as sent', function (): void {
    $doc = Doc::factory()->create([
        'status' => Draft::class,
    ]);

    $this->service->markAsSent($doc, 'Sent to test@example.com');

    expect($doc->fresh()->status->equals(Sent::class))->toBeTrue();
});
