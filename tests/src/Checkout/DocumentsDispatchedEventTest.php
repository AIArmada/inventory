<?php

declare(strict_types=1);

use AIArmada\Checkout\Events\DocumentsDispatched;
use AIArmada\Checkout\Jobs\GenerateCheckoutDocumentsJob;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Steps\DispatchDocumentGenerationStep;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

describe('DocumentsDispatched event', function (): void {
    it('fires after dispatching document generation', function (): void {
        Event::fake([DocumentsDispatched::class]);
        Bus::fake();

        config()->set('checkout.documents.generate_invoice', true);
        config()->set('checkout.documents.generate_receipt', false);
        config()->set('checkout.documents.queue', 'documents');

        $session = CheckoutSession::create([
            'cart_id' => 'cart-docs-1',
            'order_id' => 'order-123',
            'selected_payment_gateway' => 'chip',
        ]);

        $step = app(DispatchDocumentGenerationStep::class);
        $step->handle($session);

        Bus::assertDispatched(GenerateCheckoutDocumentsJob::class, function (GenerateCheckoutDocumentsJob $job) use ($session): bool {
            return $job->orderId === $session->order_id
                && $job->sessionId === $session->id
                && $job->documentTypes === ['invoice'];
        });

        Event::assertDispatched(DocumentsDispatched::class, function (DocumentsDispatched $event) use ($session): bool {
            return $event->orderId === $session->order_id
                && $event->session->is($session)
                && $event->documents === ['invoice']
                && $event->queue === 'documents';
        });
    });
});
