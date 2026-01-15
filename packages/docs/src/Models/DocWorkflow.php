<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Enums\DocType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property DocType|null $doc_type
 * @property bool $is_active
 * @property array<string, mixed>|null $rules
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DocWorkflowStep> $steps
 */
final class DocWorkflow extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'name',
        'doc_type',
        'is_active',
        'rules',
        'priority',
    ];

    public function getTable(): string
    {
        $tables = config('docs.database.tables', []);
        $prefix = config('docs.database.table_prefix', 'docs_');

        return $tables['workflows'] ?? $prefix . 'workflows';
    }

    /**
     * @return HasMany<DocWorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(DocWorkflowStep::class, 'workflow_id')->orderBy('order');
    }

    /**
     * Check if this workflow applies to the given document.
     */
    public function appliesTo(Doc $doc): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->doc_type !== null && $this->doc_type->value !== $doc->doc_type) {
            return false;
        }

        if (empty($this->rules)) {
            return true;
        }

        return $this->evaluateRules($doc);
    }

    protected static function booted(): void
    {
        static::deleting(function (DocWorkflow $workflow): void {
            $workflow->steps()->delete();
        });
    }

    /**
     * Evaluate workflow rules against a document.
     */
    protected function evaluateRules(Doc $doc): bool
    {
        $rules = $this->rules ?? [];

        foreach ($rules as $field => $condition) {
            if (! $this->evaluateCondition($doc, $field, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against the document.
     *
     * @param  array<string, mixed>|mixed  $condition
     */
    protected function evaluateCondition(Doc $doc, string $field, mixed $condition): bool
    {
        $value = $doc->getAttribute($field);

        if (is_array($condition)) {
            $operator = $condition['operator'] ?? '=';
            $compareValue = $condition['value'] ?? null;

            return match ($operator) {
                '=' => $value === $compareValue,
                '!=' => $value !== $compareValue,
                '>' => $value > $compareValue,
                '>=' => $value >= $compareValue,
                '<' => $value < $compareValue,
                '<=' => $value <= $compareValue,
                'in' => in_array($value, (array) $compareValue, true),
                'not_in' => ! in_array($value, (array) $compareValue, true),
                default => true,
            };
        }

        return $value === $condition;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'doc_type' => DocType::class,
            'is_active' => 'boolean',
            'rules' => 'array',
            'priority' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
