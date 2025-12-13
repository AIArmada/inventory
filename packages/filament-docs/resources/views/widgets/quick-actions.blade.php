<x-filament-widgets::widget>
    <x-slot name="heading">
        Quick Actions
    </x-slot>

    <div class="flex flex-wrap gap-3 p-4">
        {{ $this->createInvoiceAction }}
        {{ $this->createQuotationAction }}
        {{ $this->createCreditNoteAction }}
        {{ $this->createReceiptAction }}
        {{ $this->viewReportsAction }}
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>