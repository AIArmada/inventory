<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Tax;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class TaxDocumentService
{
    public function __construct(
        private readonly Tax1099Generator $generator,
    ) {}

    public function generateAnnualDocuments(int $year): Collection
    {
        $affiliates = $this->getAffiliatesRequiring1099($year);
        $documents = collect();

        foreach ($affiliates as $affiliate) {
            $document = $this->generate1099ForAffiliate($affiliate, $year);
            if ($document) {
                $documents->push($document);
            }
        }

        return $documents;
    }

    public function generate1099ForAffiliate(Affiliate $affiliate, int $year): ?AffiliateTaxDocument
    {
        $totalPayouts = $this->calculateAnnualPayouts($affiliate, $year);

        $threshold = config('affiliates.tax.1099_threshold', 60000);
        if ($totalPayouts < $threshold) {
            return null;
        }

        $taxInfo = $affiliate->tax_info ?? [];
        if (empty($taxInfo['tin']) || empty($taxInfo['legal_name'])) {
            return AffiliateTaxDocument::create([
                'affiliate_id' => $affiliate->id,
                'document_type' => '1099-NEC',
                'tax_year' => $year,
                'status' => 'pending_info',
                'total_amount_minor' => $totalPayouts,
                'currency' => $affiliate->currency ?? 'USD',
                'notes' => 'Missing required tax information (TIN or legal name).',
            ]);
        }

        $documentPath = $this->generator->generate([
            'affiliate' => $affiliate,
            'year' => $year,
            'total_amount' => $totalPayouts,
            'tax_info' => $taxInfo,
        ]);

        return AffiliateTaxDocument::create([
            'affiliate_id' => $affiliate->id,
            'document_type' => '1099-NEC',
            'tax_year' => $year,
            'status' => 'generated',
            'total_amount_minor' => $totalPayouts,
            'currency' => $affiliate->currency ?? 'USD',
            'document_path' => $documentPath,
            'generated_at' => now(),
        ]);
    }

    public function getAffiliatesRequiring1099(int $year): Collection
    {
        $threshold = config('affiliates.tax.1099_threshold', 60000);
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        $affiliateIds = AffiliatePayout::query()
            ->where('owner_type', Affiliate::class)
            ->where('status', PayoutStatus::Completed->value)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->groupBy('owner_id')
            ->havingRaw('SUM(total_minor) >= ?', [$threshold])
            ->pluck('owner_id');

        return Affiliate::query()
            ->whereIn('id', $affiliateIds)
            ->get();
    }

    public function calculateAnnualPayouts(Affiliate $affiliate, int $year): int
    {
        $startDate = Carbon::create($year, 1, 1)->startOfDay();
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        return (int) $affiliate->payouts()
            ->where('status', PayoutStatus::Completed->value)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('total_minor');
    }

    public function getDocumentsForAffiliate(Affiliate $affiliate): Collection
    {
        return AffiliateTaxDocument::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('tax_year')
            ->get();
    }

    public function markDocumentAsSent(AffiliateTaxDocument $document): AffiliateTaxDocument
    {
        $document->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $document;
    }

    public function regenerateDocument(AffiliateTaxDocument $document): AffiliateTaxDocument
    {
        $affiliate = $document->affiliate;

        $documentPath = $this->generator->generate([
            'affiliate' => $affiliate,
            'year' => $document->tax_year,
            'total_amount' => $document->total_amount_minor,
            'tax_info' => $affiliate->tax_info ?? [],
        ]);

        $document->update([
            'document_path' => $documentPath,
            'status' => 'generated',
            'generated_at' => now(),
        ]);

        return $document;
    }
}
