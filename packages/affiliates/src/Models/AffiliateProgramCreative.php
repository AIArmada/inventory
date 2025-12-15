<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $program_id
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property int|null $width
 * @property int|null $height
 * @property string $asset_url
 * @property string $destination_url
 * @property string $tracking_code
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram $program
 */
class AffiliateProgramCreative extends Model
{
    use HasUuids;

    protected $fillable = [
        'program_id',
        'type',
        'name',
        'description',
        'width',
        'height',
        'asset_url',
        'destination_url',
        'tracking_code',
        'metadata',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.program_creatives', 'affiliate_program_creatives');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function getTrackingUrl(Affiliate $affiliate): string
    {
        $baseUrl = $this->destination_url;
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $param = config('affiliates.links.parameter', 'aff');

        return $baseUrl . $separator . $param . '=' . $affiliate->code;
    }

    public function getEmbedCode(Affiliate $affiliate): string
    {
        $trackingUrl = $this->getTrackingUrl($affiliate);

        return match ($this->type) {
            'banner' => sprintf(
                '<a href="%s" target="_blank"><img src="%s" width="%d" height="%d" alt="%s" /></a>',
                $trackingUrl,
                $this->asset_url,
                $this->width ?? 0,
                $this->height ?? 0,
                htmlspecialchars($this->name)
            ),
            'text_link' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                $trackingUrl,
                htmlspecialchars($this->name)
            ),
            default => $trackingUrl,
        };
    }

    public function getDimensions(): ?string
    {
        if ($this->width && $this->height) {
            return "{$this->width}x{$this->height}";
        }

        return null;
    }
}
