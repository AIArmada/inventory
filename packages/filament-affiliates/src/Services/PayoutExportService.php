<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Services;

use AIArmada\Affiliates\Models\AffiliatePayout;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PayoutExportService
{
    public function download(AffiliatePayout $payout): StreamedResponse
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne(['affiliate_code', 'order_reference', 'commission_minor', 'currency', 'status']);

        foreach ($payout->conversions as $conversion) {
            $csv->insertOne([
                $conversion->affiliate_code,
                $conversion->order_reference,
                $conversion->commission_minor,
                $conversion->commission_currency,
                $conversion->status->value ?? (string) $conversion->status,
            ]);
        }

        $filename = sprintf('%s.csv', $payout->reference);

        return response()->streamDownload(
            static fn () => print $csv->toString(),
            $filename,
            [
                'Content-Type' => 'text/csv',
            ]
        );
    }
}
