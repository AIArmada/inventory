<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\GiftCards\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * @property string $id
 * @property string $code
 * @property string|null $pin
 * @property GiftCardType $type
 * @property string $currency
 * @property int $initial_balance Balance in cents
 * @property int $current_balance Balance in cents
 * @property GiftCardStatus $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property string|null $purchaser_type
 * @property string|null $purchaser_id
 * @property string|null $recipient_type
 * @property string|null $recipient_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, GiftCardTransaction> $transactions
 * @property-read int|null $used_balance
 * @property-read float $balance_utilization
 */
class GiftCard extends Model
{
    use HasOwner;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'pin',
        'type',
        'currency',
        'initial_balance',
        'current_balance',
        'status',
        'activated_at',
        'expires_at',
        'last_used_at',
        'purchaser_type',
        'purchaser_id',
        'recipient_type',
        'recipient_id',
        'owner_type',
        'owner_id',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'standard',
        'status' => 'inactive',
        'currency' => 'MYR',
    ];

    /**
     * Generate a unique gift card code.
     */
    public static function generateCode(string $prefix = 'GC'): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = mb_strtoupper(Str::random(4));
        }

        return $prefix . '-' . implode('-', $segments);
    }

    /**
     * Find a gift card by code.
     */
    public static function findByCode(string $code): ?static
    {
        /** @var static|null */
        return static::query()->where('code', mb_strtoupper($code))->first();
    }

    /**
     * Find a gift card by code or throw.
     */
    public static function findByCodeOrFail(string $code): static
    {
        $giftCard = static::findByCode($code);

        if ($giftCard === null) {
            throw new RuntimeException("Gift card not found: {$code}");
        }

        return $giftCard;
    }

    public function getTable(): string
    {
        /** @var string $table */
        $table = config('vouchers.table_names.gift_cards', 'gift_cards');

        return $table;
    }

    /**
     * @return HasMany<GiftCardTransaction, GiftCard>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class, 'gift_card_id');
    }

    /**
     * @return MorphTo<Model, GiftCard>
     */
    public function purchaser(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, GiftCard>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter active gift cards.
     *
     * @param  Builder<GiftCard>  $query
     * @return Builder<GiftCard>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GiftCardStatus::Active);
    }

    /**
     * Scope to filter by status.
     *
     * @param  Builder<GiftCard>  $query
     * @return Builder<GiftCard>
     */
    public function scopeWithStatus(Builder $query, GiftCardStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by type.
     *
     * @param  Builder<GiftCard>  $query
     * @return Builder<GiftCard>
     */
    public function scopeOfType(Builder $query, GiftCardType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter gift cards with available balance.
     *
     * @param  Builder<GiftCard>  $query
     * @return Builder<GiftCard>
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->where('current_balance', '>', 0);
    }

    /**
     * Scope to filter gift cards expiring within days.
     *
     * @param  Builder<GiftCard>  $query
     * @return Builder<GiftCard>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays($days));
    }

    /**
     * Scope to filter by recipient.
     *
     * @param  Builder<GiftCard>  $query
     * @return Builder<GiftCard>
     */
    public function scopeForRecipient(Builder $query, Model $recipient): Builder
    {
        return $query->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey());
    }

    /**
     * Check if gift card is active.
     */
    public function isActive(): bool
    {
        return $this->status === GiftCardStatus::Active;
    }

    /**
     * Check if gift card is expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === GiftCardStatus::Expired) {
            return true;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if gift card has available balance.
     */
    public function hasBalance(): bool
    {
        return $this->current_balance > 0;
    }

    /**
     * Check if gift card can be redeemed.
     */
    public function canRedeem(): bool
    {
        return $this->isActive()
            && $this->hasBalance()
            && ! $this->isExpired();
    }

    /**
     * Check if gift card can be topped up.
     */
    public function canTopUp(): bool
    {
        if (! $this->type->canBeToppedup()) {
            return false;
        }

        return $this->status->canTopUp();
    }

    /**
     * Check if gift card can be transferred.
     */
    public function canTransfer(): bool
    {
        if (! $this->type->canBeTransferred()) {
            return false;
        }

        return $this->status->canTransfer();
    }

    /**
     * Verify the PIN matches.
     */
    public function verifyPin(?string $pin): bool
    {
        if ($this->pin === null) {
            return true;
        }

        return $this->pin === $pin;
    }

    /**
     * Get the used balance (initial - current).
     */
    public function getUsedBalanceAttribute(): int
    {
        return $this->initial_balance - $this->current_balance;
    }

    /**
     * Get balance utilization as a percentage.
     */
    public function getBalanceUtilizationAttribute(): float
    {
        if ($this->initial_balance === 0) {
            return 0.0;
        }

        return round(($this->used_balance / $this->initial_balance) * 100, 2);
    }

    /**
     * Activate the gift card.
     */
    public function activate(): static
    {
        if (! $this->status->canTransitionTo(GiftCardStatus::Active)) {
            throw new RuntimeException(
                "Cannot activate gift card in {$this->status->value} status"
            );
        }

        $this->status = GiftCardStatus::Active;
        $this->activated_at = now();
        $this->save();

        $this->recordTransaction(
            type: GiftCardTransactionType::Activate,
            amount: 0,
            description: 'Gift card activated'
        );

        return $this;
    }

    /**
     * Suspend the gift card.
     */
    public function suspend(): static
    {
        if (! $this->status->canTransitionTo(GiftCardStatus::Suspended)) {
            throw new RuntimeException(
                "Cannot suspend gift card in {$this->status->value} status"
            );
        }

        $this->status = GiftCardStatus::Suspended;
        $this->save();

        return $this;
    }

    /**
     * Cancel the gift card.
     */
    public function cancel(): static
    {
        if (! $this->status->canTransitionTo(GiftCardStatus::Cancelled)) {
            throw new RuntimeException(
                "Cannot cancel gift card in {$this->status->value} status"
            );
        }

        $this->status = GiftCardStatus::Cancelled;
        $this->save();

        return $this;
    }

    /**
     * Credit the gift card balance.
     */
    public function credit(
        int $amount,
        GiftCardTransactionType $type,
        ?Model $reference = null,
        ?string $description = null,
        ?Model $actor = null,
        ?array $metadata = null
    ): GiftCardTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive');
        }

        if (! $type->isCredit()) {
            throw new InvalidArgumentException("Transaction type {$type->value} is not a credit type");
        }

        $balanceBefore = $this->current_balance;
        $this->current_balance += $amount;
        $this->save();

        // Reactivate if was exhausted
        if ($this->status === GiftCardStatus::Exhausted && $this->current_balance > 0) {
            $this->status = GiftCardStatus::Active;
            $this->save();
        }

        return $this->recordTransaction(
            type: $type,
            amount: $amount,
            balanceBefore: $balanceBefore,
            reference: $reference,
            description: $description,
            actor: $actor,
            metadata: $metadata
        );
    }

    /**
     * Debit the gift card balance.
     */
    public function debit(
        int $amount,
        GiftCardTransactionType $type,
        ?Model $reference = null,
        ?string $description = null,
        ?Model $actor = null,
        ?array $metadata = null
    ): GiftCardTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive');
        }

        if (! $type->isDebit()) {
            throw new InvalidArgumentException("Transaction type {$type->value} is not a debit type");
        }

        if ($amount > $this->current_balance) {
            throw new RuntimeException('Insufficient balance');
        }

        $balanceBefore = $this->current_balance;
        $this->current_balance -= $amount;
        $this->last_used_at = now();
        $this->save();

        // Mark as exhausted if balance is zero
        if ($this->current_balance === 0 && $this->status === GiftCardStatus::Active) {
            $this->status = GiftCardStatus::Exhausted;
            $this->save();
        }

        return $this->recordTransaction(
            type: $type,
            amount: -$amount,
            balanceBefore: $balanceBefore,
            reference: $reference,
            description: $description,
            actor: $actor,
            metadata: $metadata
        );
    }

    /**
     * Redeem gift card balance for a purchase.
     */
    public function redeem(
        int $amount,
        Model $reference,
        ?string $description = null,
        ?Model $actor = null
    ): GiftCardTransaction {
        if (! $this->canRedeem()) {
            throw new RuntimeException('Gift card cannot be redeemed');
        }

        return $this->debit(
            amount: min($amount, $this->current_balance),
            type: GiftCardTransactionType::Redeem,
            reference: $reference,
            description: $description ?? 'Redeemed for order',
            actor: $actor
        );
    }

    /**
     * Top up the gift card.
     */
    public function topUp(
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
        ?Model $actor = null
    ): GiftCardTransaction {
        if (! $this->canTopUp()) {
            throw new RuntimeException('Gift card cannot be topped up');
        }

        return $this->credit(
            amount: $amount,
            type: GiftCardTransactionType::TopUp,
            reference: $reference,
            description: $description ?? 'Top up',
            actor: $actor
        );
    }

    /**
     * Refund to the gift card.
     */
    public function refund(
        int $amount,
        Model $reference,
        ?string $description = null,
        ?Model $actor = null
    ): GiftCardTransaction {
        return $this->credit(
            amount: $amount,
            type: GiftCardTransactionType::Refund,
            reference: $reference,
            description: $description ?? 'Refund',
            actor: $actor
        );
    }

    /**
     * Transfer ownership to a new recipient.
     */
    public function transferTo(Model $newRecipient, ?Model $actor = null): static
    {
        if (! $this->canTransfer()) {
            throw new RuntimeException('Gift card cannot be transferred');
        }

        $previousRecipient = $this->recipient;

        $this->recipient_type = $newRecipient->getMorphClass();
        $this->recipient_id = $newRecipient->getKey();
        $this->save();

        $this->recordTransaction(
            type: GiftCardTransactionType::Transfer,
            amount: 0,
            description: 'Ownership transferred',
            actor: $actor,
            metadata: [
                'previous_recipient_type' => $previousRecipient?->getMorphClass(),
                'previous_recipient_id' => $previousRecipient?->getKey(),
                'new_recipient_type' => $newRecipient->getMorphClass(),
                'new_recipient_id' => $newRecipient->getKey(),
            ]
        );

        return $this;
    }

    /**
     * Expire the gift card (forfeit remaining balance).
     */
    public function expire(?Model $actor = null): static
    {
        if (! $this->status->canTransitionTo(GiftCardStatus::Expired)) {
            throw new RuntimeException(
                "Cannot expire gift card in {$this->status->value} status"
            );
        }

        $remainingBalance = $this->current_balance;

        if ($remainingBalance > 0) {
            $this->debit(
                amount: $remainingBalance,
                type: GiftCardTransactionType::Expire,
                description: 'Balance forfeited on expiration',
                actor: $actor
            );
        }

        $this->status = GiftCardStatus::Expired;
        $this->save();

        return $this;
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (GiftCard $giftCard): void {
            if (empty($giftCard->code)) {
                $giftCard->code = static::generateCode();
            }
            $giftCard->code = mb_strtoupper($giftCard->code);
        });

        static::deleting(function (GiftCard $giftCard): void {
            $giftCard->transactions()->delete();
        });
    }

    /**
     * Record a transaction.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    protected function recordTransaction(
        GiftCardTransactionType $type,
        int $amount,
        ?int $balanceBefore = null,
        ?Model $reference = null,
        ?string $description = null,
        ?Model $actor = null,
        ?array $metadata = null
    ): GiftCardTransaction {
        $balanceBefore = $balanceBefore ?? $this->current_balance;
        $balanceAfter = $balanceBefore + $amount;

        return $this->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'description' => $description,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => GiftCardType::class,
            'status' => GiftCardStatus::class,
            'initial_balance' => 'integer',
            'current_balance' => 'integer',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
