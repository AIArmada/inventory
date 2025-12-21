<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Services;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service for exporting affiliate payout data in multiple formats.
 *
 * Supports CSV, Excel (XLSX), and PDF formats.
 */
final class PayoutExportService
{
    /**
     * Download payout data as CSV.
     */
    public function downloadCsv(AffiliatePayout $payout): StreamedResponse
    {
        $payout = $this->resolveOwnerScopedPayout($payout);

        $csv = Writer::createFromFileObject(new SplTempFileObject);
        $csv->insertOne($this->getHeaders());

        foreach ($payout->conversions as $conversion) {
            $csv->insertOne($this->getRowData($conversion));
        }

        $filename = sprintf('%s.csv', $payout->reference);

        return response()->streamDownload(
            static fn () => print $csv->toString(),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * Download payout data as Excel (XLSX).
     *
     * Uses SimpleXLSXGen for Excel generation without the maatwebsite/excel dependency.
     */
    public function downloadExcel(AffiliatePayout $payout): StreamedResponse
    {
        $payout = $this->resolveOwnerScopedPayout($payout);

        $data = $this->buildExportData($payout);
        $filename = sprintf('%s.xlsx', $payout->reference);

        // Use Spatie SimpleXLSXGen or fallback to CSV-compatible Excel
        if (class_exists(\Shuchkin\SimpleXLSXGen::class)) {
            return $this->streamXlsxWithSimpleXlsx($data, $filename);
        }

        // Fallback: Generate XML-based Excel file
        return $this->streamXmlExcel($data, $filename);
    }

    /**
     * Download payout data as PDF.
     *
     * Uses Spatie Laravel PDF if available, otherwise generates basic HTML-PDF.
     */
    public function downloadPdf(AffiliatePayout $payout): StreamedResponse
    {
        $payout = $this->resolveOwnerScopedPayout($payout);

        $data = $this->buildExportData($payout);
        $filename = sprintf('%s.pdf', $payout->reference);

        // Use Spatie Laravel PDF if available
        if (class_exists(\Spatie\LaravelPdf\Facades\Pdf::class)) {
            return $this->streamWithSpatiePdf($payout, $data, $filename);
        }

        // Fallback: Use DomPDF directly if available
        if (class_exists(\Dompdf\Dompdf::class)) {
            return $this->streamWithDompdf($payout, $data, $filename);
        }

        // Last resort: Return HTML download
        return $this->streamHtml($payout, $data, $filename);
    }

    /**
     * Legacy method for backward compatibility.
     */
    public function download(AffiliatePayout $payout): StreamedResponse
    {
        return $this->downloadCsv($payout);
    }

    private function resolveOwnerScopedPayout(AffiliatePayout $payout): AffiliatePayout
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $payout->loadMissing('conversions');
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        $query = AffiliatePayout::query()->withoutGlobalScope(OwnerScope::class);
        OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);

        return $query
            ->with('conversions')
            ->whereKey($payout->getKey())
            ->firstOrFail();
    }

    /**
     * Get export headers.
     *
     * @return array<string>
     */
    private function getHeaders(): array
    {
        return [
            'Affiliate Code',
            'Order Reference',
            'Commission Amount',
            'Currency',
            'Status',
            'Conversion Date',
        ];
    }

    /**
     * Get row data for a conversion.
     *
     * @return array<string>
     */
    private function getRowData(object $conversion): array
    {
        return [
            (string) $conversion->affiliate_code,
            (string) $conversion->order_reference,
            number_format((int) $conversion->commission_minor / 100, 2),
            (string) $conversion->commission_currency,
            $conversion->status->value ?? (string) $conversion->status,
            $conversion->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * Build export data array.
     *
     * @return array<int, array<string>>
     */
    private function buildExportData(AffiliatePayout $payout): array
    {
        $data = [$this->getHeaders()];

        foreach ($payout->conversions as $conversion) {
            $data[] = $this->getRowData($conversion);
        }

        return $data;
    }

    /**
     * Stream XLSX using SimpleXLSXGen library.
     *
     * @param  array<int, array<string>>  $data
     */
    private function streamXlsxWithSimpleXlsx(array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($data): void {
                /** @phpstan-ignore class.notFound */
                $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($data);
                /** @phpstan-ignore method.nonObject */
                $xlsx->saveAs('php://output');
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /**
     * Stream XML-based Excel file (fallback).
     *
     * @param  array<int, array<string>>  $data
     */
    private function streamXmlExcel(array $data, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($data): void {
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
                echo '<Worksheet ss:Name="Payout"><Table>' . "\n";

                foreach ($data as $row) {
                    echo '<Row>';
                    foreach ($row as $cell) {
                        echo sprintf('<Cell><Data ss:Type="String">%s</Data></Cell>', htmlspecialchars((string) $cell));
                    }
                    echo '</Row>' . "\n";
                }

                echo '</Table></Worksheet></Workbook>';
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.ms-excel',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    /**
     * Stream PDF using Spatie Laravel PDF.
     *
     * @param  array<int, array<string>>  $data
     */
    private function streamWithSpatiePdf(AffiliatePayout $payout, array $data, string $filename): StreamedResponse
    {
        $html = $this->buildPdfHtml($payout, $data);

        return response()->streamDownload(
            function () use ($html): void {
                /** @phpstan-ignore method.notFound */
                $pdf = \Spatie\LaravelPdf\Facades\Pdf::html($html)->base64();
                echo base64_decode($pdf);
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Stream PDF using DomPDF directly.
     *
     * @param  array<int, array<string>>  $data
     */
    private function streamWithDompdf(AffiliatePayout $payout, array $data, string $filename): StreamedResponse
    {
        $html = $this->buildPdfHtml($payout, $data);

        return response()->streamDownload(
            function () use ($html): void {
                /** @phpstan-ignore class.notFound */
                $dompdf = new \Dompdf\Dompdf;
                /** @phpstan-ignore-next-line */
                $dompdf->loadHtml($html);
                /** @phpstan-ignore-next-line */
                $dompdf->setPaper('A4', 'portrait');
                /** @phpstan-ignore-next-line */
                $dompdf->render();
                /** @phpstan-ignore-next-line */
                echo $dompdf->output();
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Stream HTML download (fallback when no PDF library available).
     *
     * @param  array<int, array<string>>  $data
     */
    private function streamHtml(AffiliatePayout $payout, array $data, string $filename): StreamedResponse
    {
        $html = $this->buildPdfHtml($payout, $data);

        return response()->streamDownload(
            static fn () => print $html,
            str_replace('.pdf', '.html', $filename),
            ['Content-Type' => 'text/html']
        );
    }

    /**
     * Build HTML content for PDF generation.
     *
     * @param  array<int, array<string>>  $data
     */
    private function buildPdfHtml(AffiliatePayout $payout, array $data): string
    {
        $headers = array_shift($data);
        $rows = $data;

        $totalCommission = collect($payout->conversions)->sum('commission_minor') / 100;
        $currency = $payout->conversions->first()?->commission_currency ?? 'USD';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payout Report - {$payout->reference}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h1 { color: #333; font-size: 18px; }
        .meta { margin-bottom: 20px; }
        .meta p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f5f5f5; text-align: left; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .total { font-weight: bold; background: #f9f9f9; }
        tr:nth-child(even) { background: #fafafa; }
    </style>
</head>
<body>
    <h1>Affiliate Payout Report</h1>
    <div class="meta">
        <p><strong>Reference:</strong> {$payout->reference}</p>
        <p><strong>Status:</strong> {$this->getStatusValue($payout)}</p>
        <p><strong>Generated:</strong> {$payout->created_at->format('Y-m-d H:i:s')}</p>
        <p><strong>Total Conversions:</strong> {$payout->conversions->count()}</p>
        <p><strong>Total Amount:</strong> {$currency} " . number_format($totalCommission, 2) . "</p>
    </div>
    <table>
        <thead>
            <tr>
HTML;

        foreach ($headers ?? [] as $header) {
            $html .= sprintf('<th>%s</th>', htmlspecialchars((string) $header));
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= sprintf('<td>%s</td>', htmlspecialchars((string) $cell));
            }
            $html .= '</tr>';
        }

        $html .= <<<'HTML'
        </tbody>
    </table>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Get status value as string (handles both enum and string types).
     */
    private function getStatusValue(AffiliatePayout $payout): string
    {
        $status = $payout->status;

        if (is_object($status) && property_exists($status, 'value')) {
            return (string) $status->value;
        }

        return (string) $status;
    }
}
