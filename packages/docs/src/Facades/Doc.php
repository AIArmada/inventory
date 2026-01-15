<?php

declare(strict_types=1);

namespace AIArmada\Docs\Facades;

use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocVersion;
use AIArmada\Docs\Services\DocService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generateNumber(string $docType = 'invoice')
 * @method static string resolveStorageDiskForDocType(string $docType)
 * @method static \AIArmada\Docs\Models\Doc create(DocData $data)
 * @method static \AIArmada\Docs\Models\Doc createFromType(DocType $type, array $data, ?Model $owner = null)
 * @method static \AIArmada\Docs\Models\Doc update(\AIArmada\Docs\Models\Doc $doc, array $data)
 * @method static \AIArmada\Docs\Models\Doc convert(\AIArmada\Docs\Models\Doc $source, DocType $targetType, ?Model $owner = null)
 * @method static DocPayment recordPayment(\AIArmada\Docs\Models\Doc $doc, array $paymentData)
 * @method static \AIArmada\Docs\Models\Doc clone(\AIArmada\Docs\Models\Doc $source, ?Model $owner = null)
 * @method static DocVersion createVersion(\AIArmada\Docs\Models\Doc $doc, ?string $summary = null)
 * @method static string generatePdf(\AIArmada\Docs\Models\Doc $doc, bool $save = true)
 * @method static string downloadPdf(\AIArmada\Docs\Models\Doc $doc)
 * @method static void markAsSent(\AIArmada\Docs\Models\Doc $doc, ?string $notes = null)
 * @method static void updateStatus(\AIArmada\Docs\Models\Doc $doc, DocStatus $status, ?string $notes = null)
 * @method static array calculateTotals(array $items, float $discountAmount = 0)
 *
 * @see DocService
 */
class Doc extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'doc';
    }
}
