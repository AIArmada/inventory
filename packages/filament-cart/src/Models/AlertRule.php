<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $event_type
 * @property array<string, mixed> $conditions
 * @property bool $notify_email
 * @property bool $notify_slack
 * @property bool $notify_webhook
 * @property bool $notify_database
 * @property array<string>|null $email_recipients
 * @property string|null $slack_webhook_url
 * @property string|null $webhook_url
 * @property int $cooldown_minutes
 * @property Carbon|null $last_triggered_at
 * @property string $severity
 * @property int $priority
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AlertLog> $logs
 */
class AlertRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'event_type',
        'conditions',
        'notify_email',
        'notify_slack',
        'notify_webhook',
        'notify_database',
        'email_recipients',
        'slack_webhook_url',
        'webhook_url',
        'cooldown_minutes',
        'last_triggered_at',
        'severity',
        'priority',
        'is_active',
    ];

    public function getTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['alert_rules'] ?? $prefix . 'alert_rules';
    }

    /**
     * @return HasMany<AlertLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AlertLog::class, 'alert_rule_id');
    }

    /**
     * Check if the rule is in cooldown period.
     */
    public function isInCooldown(): bool
    {
        if ($this->last_triggered_at === null) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }

    /**
     * Get minutes remaining in cooldown.
     */
    public function getCooldownRemainingMinutes(): int
    {
        if (! $this->isInCooldown()) {
            return 0;
        }

        return (int) now()->diffInMinutes($this->last_triggered_at->addMinutes($this->cooldown_minutes));
    }

    /**
     * Mark the rule as triggered.
     */
    public function markTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Get enabled notification channels.
     *
     * @return array<string>
     */
    public function getEnabledChannels(): array
    {
        $channels = [];

        if ($this->notify_email) {
            $channels[] = 'email';
        }
        if ($this->notify_slack) {
            $channels[] = 'slack';
        }
        if ($this->notify_webhook) {
            $channels[] = 'webhook';
        }
        if ($this->notify_database) {
            $channels[] = 'database';
        }

        return $channels;
    }

    protected static function booted(): void
    {
        static::deleting(function (AlertRule $rule): void {
            $rule->logs()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'email_recipients' => 'array',
            'notify_email' => 'boolean',
            'notify_slack' => 'boolean',
            'notify_webhook' => 'boolean',
            'notify_database' => 'boolean',
            'is_active' => 'boolean',
            'cooldown_minutes' => 'integer',
            'priority' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }
}
