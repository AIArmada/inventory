<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\Docs\Enums\DocStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocStatusHistory extends Model
{
    use HasFactory;
    use HasUuids;

    public function getTable(): string
    {
        return config('docs.database.tables.doc_status_histories', 'doc_status_histories');
    }

    protected $fillable = [
        'doc_id',
        'status',
        'notes',
        'changed_by',
    ];

    protected $casts = [
        'status' => DocStatus::class,
    ];

    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }
}
