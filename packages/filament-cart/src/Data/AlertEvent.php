<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Alert event DTO for dispatching alerts.
 */
class AlertEvent extends Data
{
    public function __construct(
        public string $event_type,
        public string $severity,
        public string $title,
        public string $message,
        public ?string $cart_id,
        public ?string $session_id,
        /** @var array<string, mixed> */
        public array $data,
        public Carbon $occurred_at,
    ) {}

    /**
     * Create from cart abandonment.
     *
     * @param  array<string, mixed>  $cartData
     */
    public static function fromAbandonment(string $cartId, string $sessionId, array $cartData): self
    {
        $valueCents = $cartData['value_cents'] ?? 0;
        $formattedValue = '$' . number_format($valueCents / 100, 2);

        return new self(
            event_type: 'abandonment',
            severity: $valueCents >= 10000 ? 'warning' : 'info',
            title: 'Cart Abandoned',
            message: "A cart worth {$formattedValue} was abandoned.",
            cart_id: $cartId,
            session_id: $sessionId,
            data: $cartData,
            occurred_at: now(),
        );
    }

    /**
     * Create from fraud signal.
     *
     * @param  array<string, mixed>  $fraudData
     */
    public static function fromFraud(string $cartId, string $sessionId, array $fraudData): self
    {
        $riskScore = $fraudData['risk_score'] ?? 0;

        return new self(
            event_type: 'fraud',
            severity: $riskScore >= 0.8 ? 'critical' : 'warning',
            title: 'Fraud Signal Detected',
            message: "Suspicious activity detected with risk score of {$riskScore}.",
            cart_id: $cartId,
            session_id: $sessionId,
            data: $fraudData,
            occurred_at: now(),
        );
    }

    /**
     * Create from high value cart.
     *
     * @param  array<string, mixed>  $cartData
     */
    public static function fromHighValue(string $cartId, string $sessionId, array $cartData): self
    {
        $valueCents = $cartData['value_cents'] ?? 0;
        $formattedValue = '$' . number_format($valueCents / 100, 2);

        return new self(
            event_type: 'high_value',
            severity: 'info',
            title: 'High-Value Cart',
            message: "A high-value cart worth {$formattedValue} is active.",
            cart_id: $cartId,
            session_id: $sessionId,
            data: $cartData,
            occurred_at: now(),
        );
    }

    /**
     * Create from recovery opportunity.
     *
     * @param  array<string, mixed>  $cartData
     */
    public static function fromRecoveryOpportunity(string $cartId, string $sessionId, array $cartData): self
    {
        return new self(
            event_type: 'recovery',
            severity: 'info',
            title: 'Recovery Opportunity',
            message: 'A cart may be recoverable with timely intervention.',
            cart_id: $cartId,
            session_id: $sessionId,
            data: $cartData,
            occurred_at: now(),
        );
    }

    /**
     * Create a custom alert event.
     *
     * @param  array<string, mixed>  $data
     */
    public static function custom(
        string $eventType,
        string $severity,
        string $title,
        string $message,
        array $data = [],
        ?string $cartId = null,
        ?string $sessionId = null,
    ): self {
        return new self(
            event_type: $eventType,
            severity: $severity,
            title: $title,
            message: $message,
            cart_id: $cartId,
            session_id: $sessionId,
            data: $data,
            occurred_at: now(),
        );
    }

    /**
     * Check if this is a critical severity event.
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Check if this is a warning severity event.
     */
    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }
}
