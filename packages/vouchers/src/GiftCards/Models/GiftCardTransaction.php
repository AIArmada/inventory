<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Models;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $gift_card_id
 * @property GiftCardTransactionType $type
 * @property int $amount Positive for credit, negative for debit
 * @property int $balance_before
 * @property int $balance_after
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $description
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GiftCard $giftCard
 * @property-read bool $is_credit
 * @property-read bool $is_debit
 */
class GiftCardTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'gift_card_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    /**
     * Factory method to record a redemption.
     */
    public static function recordRedemption(
        GiftCard $giftCard,
        int $amount,
        Model $reference,
        ?string $description = null,
        ?Model $actor = null
    ): static {
        $balanceBefore = $giftCard->current_balance;

        /** @var static */
        return static::query()->create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Redeem,
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $amount,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'description' => $description ?? 'Redeemed',
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);
    }

    /**
     * Factory method to record a top-up.
     */
    public static function recordTopUp(
        GiftCard $giftCard,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        ?Model $actor = null
    ): static {
        $balanceBefore = $giftCard->current_balance;

        /** @var static */
        return static::query()->create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::TopUp,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + $amount,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $description ?? 'Top up',
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);
    }

    /**
     * Factory method to record a refund.
     */
    public static function recordRefund(
        GiftCard $giftCard,
        int $amount,
        Model $reference,
        ?string $description = null,
        ?Model $actor = null
    ): static {
        $balanceBefore = $giftCard->current_balance;

        /** @var static */
        return static::query()->create([
            'gift_card_id' => $giftCard->id,
            'type' => GiftCardTransactionType::Refund,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + $amount,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'description' => $description ?? 'Refund',
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
        ]);
    }

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['gift_card_transactions'] ?? $prefix.'gift_card_transactions';
    }

    /**
     * @return BelongsTo<GiftCard, GiftCardTransaction>
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class, 'gift_card_id');
    }

    /**
     * @return MorphTo<Model, GiftCardTransaction>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, GiftCardTransaction>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by transaction type.
     *
     * @param  Builder<GiftCardTransaction>  $query
     * @return Builder<GiftCardTransaction>
     */
    public function scopeOfType(Builder $query, GiftCardTransactionType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter credits only.
     *
     * @param  Builder<GiftCardTransaction>  $query
     * @return Builder<GiftCardTransaction>
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('amount', '>', 0);
    }

    /**
     * Scope to filter debits only.
     *
     * @param  Builder<GiftCardTransaction>  $query
     * @return Builder<GiftCardTransaction>
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('amount', '<', 0);
    }

    /**
     * Scope to filter by reference.
     *
     * @param  Builder<GiftCardTransaction>  $query
     * @return Builder<GiftCardTransaction>
     */
    public function scopeForReference(Builder $query, Model $reference): Builder
    {
        return $query->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey());
    }

    /**
     * Scope to filter transactions within date range.
     *
     * @param  Builder<GiftCardTransaction>  $query
     * @return Builder<GiftCardTransaction>
     */
    public function scopeOccurredBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Check if this is a credit transaction.
     */
    public function getIsCreditAttribute(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this is a debit transaction.
     */
    public function getIsDebitAttribute(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get the absolute amount.
     */
    public function getAbsoluteAmount(): int
    {
        return abs($this->amount);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => GiftCardTransactionType::class,
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }
}
