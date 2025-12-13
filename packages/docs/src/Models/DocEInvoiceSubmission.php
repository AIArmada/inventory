<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * E-Invoice submission tracking for Malaysian MyInvois integration.
 *
 * @property string $id
 * @property string $doc_id
 * @property string $submission_uid
 * @property string|null $document_uuid
 * @property string|null $long_id
 * @property string $status
 * @property string|null $validation_status
 * @property array<string, mixed>|null $errors
 * @property array<string, mixed>|null $warnings
 * @property string|null $ubl_xml
 * @property string|null $qr_code_url
 * @property Carbon|null $submitted_at
 * @property Carbon|null $validated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Doc $doc
 */
final class DocEInvoiceSubmission extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'doc_id',
        'submission_uid',
        'document_uuid',
        'long_id',
        'status',
        'validation_status',
        'errors',
        'warnings',
        'ubl_xml',
        'qr_code_url',
        'submitted_at',
        'validated_at',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_einvoice_submissions', 'docs_einvoice_submissions');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isValid(): bool
    {
        return $this->validation_status === 'valid';
    }

    public function isRejected(): bool
    {
        return $this->validation_status === 'invalid';
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get the MyInvois portal URL for this submission.
     */
    public function getPortalUrl(): ?string
    {
        if (! $this->long_id) {
            return null;
        }

        $baseUrl = config('docs.einvoice.sandbox', true)
            ? 'https://preprod.myinvois.hasil.gov.my'
            : 'https://myinvois.hasil.gov.my';

        return $baseUrl . '/document/' . $this->long_id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'warnings' => 'array',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }
}
