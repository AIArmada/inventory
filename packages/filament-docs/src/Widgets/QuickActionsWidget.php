<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\Docs\Enums\DocType;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;

final class QuickActionsWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament-docs::widgets.quick-actions';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    public function createInvoiceAction(): Action
    {
        return Action::make('createInvoice')
            ->label('New Invoice')
            ->icon('heroicon-o-document-plus')
            ->color('primary')
            ->url(fn () => route('filament.admin.resources.docs.create', ['type' => DocType::Invoice->value]));
    }

    public function createQuotationAction(): Action
    {
        return Action::make('createQuotation')
            ->label('New Quotation')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('info')
            ->url(fn () => route('filament.admin.resources.docs.create', ['type' => DocType::Quotation->value]));
    }

    public function createCreditNoteAction(): Action
    {
        return Action::make('createCreditNote')
            ->label('New Credit Note')
            ->icon('heroicon-o-document-minus')
            ->color('danger')
            ->url(fn () => route('filament.admin.resources.docs.create', ['type' => DocType::CreditNote->value]));
    }

    public function createReceiptAction(): Action
    {
        return Action::make('createReceipt')
            ->label('New Receipt')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->url(fn () => route('filament.admin.resources.docs.create', ['type' => DocType::Receipt->value]));
    }

    public function viewReportsAction(): Action
    {
        return Action::make('viewReports')
            ->label('Aging Report')
            ->icon('heroicon-o-chart-bar')
            ->color('warning')
            ->url(fn () => route('filament.admin.pages.aging-report'));
    }
}
