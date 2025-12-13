<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $status
 * @property bool $is_default
 * @property string|null $email_subject
 * @property string|null $email_preheader
 * @property string|null $email_body_html
 * @property string|null $email_body_text
 * @property string|null $email_from_name
 * @property string|null $email_from_email
 * @property string|null $sms_body
 * @property string|null $push_title
 * @property string|null $push_body
 * @property string|null $push_icon
 * @property string|null $push_action_url
 * @property int $times_used
 * @property int $times_opened
 * @property int $times_clicked
 * @property int $times_converted
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecoveryAttempt> $attempts
 */
class RecoveryTemplate extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'is_default',
        'email_subject',
        'email_preheader',
        'email_body_html',
        'email_body_text',
        'email_from_name',
        'email_from_email',
        'sms_body',
        'push_title',
        'push_body',
        'push_icon',
        'push_action_url',
        'times_used',
        'times_opened',
        'times_clicked',
        'times_converted',
    ];

    public function getTable(): string
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $prefix . 'recovery_templates';
    }

    /**
     * @return HasMany<RecoveryAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(RecoveryAttempt::class, 'template_id');
    }

    /**
     * Render template content with variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderSubject(array $variables): string
    {
        return $this->replaceVariables($this->email_subject ?? '', $variables);
    }

    /**
     * Render email HTML body with variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderHtmlBody(array $variables): string
    {
        return $this->replaceVariables($this->email_body_html ?? '', $variables);
    }

    /**
     * Render email text body with variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderTextBody(array $variables): string
    {
        return $this->replaceVariables($this->email_body_text ?? '', $variables);
    }

    /**
     * Render SMS body with variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderSmsBody(array $variables): string
    {
        return $this->replaceVariables($this->sms_body ?? '', $variables);
    }

    /**
     * Render push notification with variables.
     *
     * @param  array<string, mixed>  $variables
     * @return array{title: string, body: string, icon: string|null, action_url: string|null}
     */
    public function renderPush(array $variables): array
    {
        return [
            'title' => $this->replaceVariables($this->push_title ?? '', $variables),
            'body' => $this->replaceVariables($this->push_body ?? '', $variables),
            'icon' => $this->push_icon,
            'action_url' => $this->replaceVariables($this->push_action_url ?? '', $variables),
        ];
    }

    public function getOpenRate(): float
    {
        return $this->times_used > 0
            ? $this->times_opened / $this->times_used
            : 0.0;
    }

    public function getClickRate(): float
    {
        return $this->times_used > 0
            ? $this->times_clicked / $this->times_used
            : 0.0;
    }

    public function getConversionRate(): float
    {
        return $this->times_used > 0
            ? $this->times_converted / $this->times_used
            : 0.0;
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return $content;
    }
}
