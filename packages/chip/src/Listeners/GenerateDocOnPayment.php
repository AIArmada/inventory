<?php

declare(strict_types=1);

namespace AIArmada\Chip\Listeners;

use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Models\Purchase;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Services\DocService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;

/**
 * Generates invoice/receipt document when a purchase is paid.
 */
final class GenerateDocOnPayment implements ShouldQueue
{
    public function handle(PurchasePaid $event): void
    {
        if (! class_exists(DocService::class)) {
            return;
        }

        $docType = config('chip.integrations.docs.paid_doc_type', 'invoice');

        if ($docType === null || $docType === false) {
            return;
        }

        // Find the persisted Purchase model
        $purchase = Purchase::find($event->getPurchaseId());

        if ($purchase === null) {
            return;
        }

        // Skip if doc already exists for this purchase
        if ($this->docExistsForPurchase($purchase)) {
            return;
        }

        $this->generateDoc($purchase, $event, (string) $docType);
    }

    private function generateDoc(Purchase $purchase, PurchasePaid $event, string $docType): void
    {
        /** @var DocService $docService */
        $docService = app(DocService::class);

        $docData = $this->buildDocData($purchase, $event, $docType);
        $docService->createDoc($docData);
    }

    private function buildDocData(Purchase $purchase, PurchasePaid $event, string $docType): DocData
    {
        $customerData = $this->extractCustomerData($event);
        $items = $this->extractItems($purchase, $event);
        $amount = $event->getAmount();
        $currency = $event->getCurrency();

        // Determine status - since payment is already complete, mark as PAID
        $status = DocStatus::PAID;

        return new DocData(
            docType: $docType,
            status: $status,
            issueDate: now(),
            dueDate: null, // Already paid
            subtotal: $amount / 100, // Convert from cents
            taxAmount: 0, // Can be extracted from metadata if needed
            discountAmount: 0,
            total: $amount / 100,
            currency: $currency,
            customerData: $customerData,
            items: $items,
            notes: $this->generateNotes($purchase, $event),
            docableType: Purchase::class,
            docableId: (string) $purchase->id,
            generatePdf: config('chip.integrations.docs.generate_pdf', true),
            metadata: [
                'chip_purchase_id' => $event->getPurchaseId(),
                'chip_reference' => $event->getReference(),
                'payment_method' => $event->getPaymentMethod(),
                'is_test' => $event->isTest(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCustomerData(PurchasePaid $event): array
    {
        return [
            'name' => $event->getCustomerName(),
            'email' => $event->getCustomerEmail(),
        ];
    }

    /**
     * Extract items from purchase data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(Purchase $purchase, PurchasePaid $event): array
    {
        $purchaseData = $purchase->purchase ?? [];
        $products = Arr::get($purchaseData, 'products', []);

        if (empty($products)) {
            // Fallback to single line item
            return [
                [
                    'description' => Arr::get($purchaseData, 'description', 'Payment'),
                    'quantity' => 1,
                    'price' => $event->getAmount() / 100,
                    'total' => $event->getAmount() / 100,
                ],
            ];
        }

        $items = [];
        foreach ($products as $product) {
            $items[] = [
                'description' => Arr::get($product, 'name', 'Product'),
                'quantity' => Arr::get($product, 'quantity', 1),
                'price' => Arr::get($product, 'price', 0) / 100,
                'total' => (Arr::get($product, 'price', 0) * Arr::get($product, 'quantity', 1)) / 100,
            ];
        }

        return $items;
    }

    private function generateNotes(Purchase $purchase, PurchasePaid $event): string
    {
        $notes = [];

        if ($event->getReference()) {
            $notes[] = 'Reference: ' . $event->getReference();
        }

        if ($event->getPaymentMethod()) {
            $notes[] = 'Payment Method: ' . ucfirst((string) $event->getPaymentMethod());
        }

        return implode("\n", $notes);
    }

    private function docExistsForPurchase(Purchase $purchase): bool
    {
        if (! class_exists(\AIArmada\Docs\Models\Doc::class)) {
            return false;
        }

        return \AIArmada\Docs\Models\Doc::query()
            ->where('docable_type', Purchase::class)
            ->where('docable_id', $purchase->id)
            ->where('doc_type', config('chip.integrations.docs.paid_doc_type', 'invoice'))
            ->exists();
    }
}
