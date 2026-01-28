<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Events\DocumentsDispatched;
use AIArmada\Checkout\Jobs\GenerateCheckoutDocumentsJob;
use AIArmada\Checkout\Models\CheckoutSession;

final class DispatchDocumentGenerationStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'dispatch_documents';
    }

    public function getName(): string
    {
        return 'Dispatch Document Generation';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['create_order'];
    }

    public function canSkip(CheckoutSession $session): bool
    {
        // Skip if docs package is not available
        if (! class_exists(\AIArmada\Docs\DocsServiceProvider::class)) {
            return true;
        }

        // Skip if no documents are configured to generate
        $generateInvoice = config('checkout.documents.generate_invoice', true);
        $generateReceipt = config('checkout.documents.generate_receipt', true);

        return ! $generateInvoice && ! $generateReceipt;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if ($session->order_id === null) {
            return $this->failed('No order ID available for document generation');
        }

        $documentsToGenerate = [];

        if (config('checkout.documents.generate_invoice', true)) {
            $documentsToGenerate[] = 'invoice';
        }

        if (config('checkout.documents.generate_receipt', true)) {
            $documentsToGenerate[] = 'receipt';
        }

        if (empty($documentsToGenerate)) {
            return $this->skipped('No documents configured for generation');
        }

        // Dispatch the job to generate documents
        $queue = config('checkout.documents.queue', 'default');

        GenerateCheckoutDocumentsJob::dispatch(
            sessionId: $session->id,
            orderId: $session->order_id,
            documentTypes: $documentsToGenerate,
        )->onQueue($queue);

        event(new DocumentsDispatched(
            session: $session,
            orderId: $session->order_id,
            documents: $documentsToGenerate,
            queue: $queue,
        ));

        return $this->success('Document generation dispatched', [
            'documents' => $documentsToGenerate,
            'order_id' => $session->order_id,
            'queue' => $queue,
        ]);
    }
}
