<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alert_rule_id
 * @property string $event_type
 * @property string $severity
 * @property string $title
 * @property string|null $message
 * @property array<string, mixed> $event_data
 * @property array<string> $channels_notified
 * @property string|null $cart_id
 * @property string|null $session_id
 * @property bool $is_read
 * @property Carbon|null $read_at
 * @property string|null $read_by
 * @property bool $action_taken
 * @property string|null $action_type
 * @property Carbon|null $action_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AlertRule $alertRule
 */
class AlertLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'alert_rule_id',
        'event_type',
        'severity',
        'title',
        'message',
        'event_data',
        'channels_notified',
        'cart_id',
        'session_id',
        'is_read',
        'read_at',
        'read_by',
        'action_taken',
        'action_type',
        'action_at',
    ];

    public function getTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['alert_logs'] ?? $prefix . 'alert_logs';
    }

    /**
     * @return BelongsTo<AlertRule, $this>
     */
    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    /**
     * Mark the alert as read.
     */
    public function markAsRead(?string $userId = null): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
            'read_by' => $userId,
        ]);
    }

    /**
     * Mark the alert as unread.
     */
    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
            'read_by' => null,
        ]);
    }

    /**
     * Record an action taken on the alert.
     */
    public function recordAction(string $actionType): void
    {
        $this->update([
            'action_taken' => true,
            'action_type' => $actionType,
            'action_at' => now(),
        ]);
    }

    /**
     * Check if the alert is critical.
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Check if the alert is a warning.
     */
    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    /**
     * Get a display color for the severity.
     */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'channels_notified' => 'array',
            'is_read' => 'boolean',
            'action_taken' => 'boolean',
            'read_at' => 'datetime',
            'action_at' => 'datetime',
        ];
    }
}
