<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks emails sent for documents.
 *
 * @property string $id
 * @property string $doc_id
 * @property string|null $doc_email_template_id
 * @property string $recipient_email
 * @property string|null $recipient_name
 * @property string $subject
 * @property string $body
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $clicked_at
 * @property int $open_count
 * @property int $click_count
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Doc $doc
 * @property-read DocEmailTemplate|null $template
 */
final class DocEmail extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'doc_id',
        'doc_email_template_id',
        'recipient_email',
        'recipient_name',
        'subject',
        'body',
        'status',
        'sent_at',
        'opened_at',
        'clicked_at',
        'open_count',
        'click_count',
        'failure_reason',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_emails', 'docs_emails');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    /**
     * @return BelongsTo<DocEmailTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DocEmailTemplate::class, 'doc_email_template_id');
    }

    public function markAsOpened(): void
    {
        $this->increment('open_count');

        if (! $this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    public function markAsClicked(): void
    {
        $this->increment('click_count');

        if (! $this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isOpened(): bool
    {
        return $this->opened_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'open_count' => 'integer',
            'click_count' => 'integer',
            'metadata' => 'array',
        ];
    }
}
