<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $view_name
 * @property string $doc_type
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DocTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    public function getTable(): string
    {
        return config('docs.database.tables.doc_templates', 'doc_templates');
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'view_name',
        'doc_type',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    public function docs(): HasMany
    {
        return $this->hasMany(Doc::class);
    }

    /**
     * Set this template as default
     */
    public function setAsDefault(): void
    {
        // Remove default from all other templates of the same type
        static::where('id', '!=', $this->id)
            ->where('doc_type', $this->doc_type)
            ->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Scope to get default template
     *
     * @param  \Illuminate\Database\Eloquent\Builder<DocTemplate>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function default($query, ?string $docType = null): ?self
    {
        $query = $query->where('is_default', true);

        if ($docType) {
            $query->where('doc_type', $docType);
        }

        return $query->first();
    }
}
