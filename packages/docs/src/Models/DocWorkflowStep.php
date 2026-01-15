<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $workflow_id
 * @property string $name
 * @property int $order
 * @property string $action_type
 * @property array<string, mixed>|null $action_config
 * @property array<string, mixed>|null $conditions
 * @property bool $is_required
 * @property int|null $timeout_hours
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read DocWorkflow $workflow
 */
final class DocWorkflowStep extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    public const ACTION_APPROVAL = 'approval';

    public const ACTION_NOTIFICATION = 'notification';

    public const ACTION_STATUS_CHANGE = 'status_change';

    public const ACTION_WEBHOOK = 'webhook';

    protected $fillable = [
        'workflow_id',
        'owner_type',
        'owner_id',
        'name',
        'order',
        'action_type',
        'action_config',
        'conditions',
        'is_required',
        'timeout_hours',
    ];

    public function getTable(): string
    {
        $tables = config('docs.database.tables', []);
        $prefix = config('docs.database.table_prefix', 'docs_');

        return $tables['workflow_steps'] ?? $prefix . 'workflow_steps';
    }

    /**
     * @return BelongsTo<DocWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DocWorkflow::class, 'workflow_id');
    }

    /**
     * Check if this step requires approval.
     */
    public function requiresApproval(): bool
    {
        return $this->action_type === self::ACTION_APPROVAL;
    }

    /**
     * Check if this step sends notifications.
     */
    public function sendsNotification(): bool
    {
        return $this->action_type === self::ACTION_NOTIFICATION;
    }

    /**
     * Get the approvers for this step.
     *
     * @return array<int, string>
     */
    public function getApprovers(): array
    {
        if ($this->action_type !== self::ACTION_APPROVAL) {
            return [];
        }

        $config = $this->action_config ?? [];

        return $config['approvers'] ?? [];
    }

    /**
     * Get the notification recipients for this step.
     *
     * @return array<int, string>
     */
    public function getNotificationRecipients(): array
    {
        $config = $this->action_config ?? [];

        return $config['recipients'] ?? [];
    }

    /**
     * Check if conditions are met for this step.
     */
    public function conditionsMet(Doc $doc): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $field => $condition) {
            $value = $doc->getAttribute($field);

            if (is_array($condition)) {
                $operator = $condition['operator'] ?? '=';
                $compareValue = $condition['value'] ?? null;

                $result = match ($operator) {
                    '=' => $value === $compareValue,
                    '!=' => $value !== $compareValue,
                    '>' => $value > $compareValue,
                    '>=' => $value >= $compareValue,
                    '<' => $value < $compareValue,
                    '<=' => $value <= $compareValue,
                    default => true,
                };

                if (! $result) {
                    return false;
                }
            } elseif ($value !== $condition) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'action_config' => 'array',
            'conditions' => 'array',
            'is_required' => 'boolean',
            'timeout_hours' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
