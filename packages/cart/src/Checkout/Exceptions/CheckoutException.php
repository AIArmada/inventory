<?php

declare(strict_types=1);

namespace AIArmada\Cart\Checkout\Exceptions;

use AIArmada\Cart\Exceptions\CartException;
use Throwable;

/**
 * Exception thrown during checkout process.
 */
final class CheckoutException extends CartException
{
    private string $stageName = '';

    /**
     * @var array<string, string>
     */
    private array $stageErrors = [];

    /**
     * Create exception for a failed stage.
     */
    public static function stageFailed(
        string $stage,
        string $message,
        ?Throwable $previous = null
    ): self {
        $exception = new self(
            "Checkout failed at stage '{$stage}': {$message}",
            0,
            $previous
        );
        $exception->stageName = $stage;

        return $exception;
    }

    /**
     * Create exception for validation failures.
     *
     * @param  array<string, string>  $errors
     */
    public static function validationFailed(array $errors): self
    {
        $exception = new self(
            'Cart validation failed: '.implode(', ', $errors)
        );
        $exception->stageName = 'validation';
        $exception->stageErrors = $errors;

        return $exception;
    }

    /**
     * Create exception for empty cart.
     */
    public static function emptyCart(): self
    {
        $exception = new self('Cannot checkout an empty cart');
        $exception->stageName = 'validation';

        return $exception;
    }

    /**
     * Create exception for inventory reservation failure.
     */
    public static function reservationFailed(string $itemName, int $requested, int $available): self
    {
        $exception = new self(
            "Cannot reserve {$requested} units of '{$itemName}'. Only {$available} available."
        );
        $exception->stageName = 'reservation';

        return $exception;
    }

    /**
     * Create exception for payment failure.
     */
    public static function paymentFailed(string $message, ?string $gatewayCode = null): self
    {
        $fullMessage = $gatewayCode
            ? "Payment failed [{$gatewayCode}]: {$message}"
            : "Payment failed: {$message}";

        $exception = new self($fullMessage);
        $exception->stageName = 'payment';

        return $exception;
    }

    /**
     * Get the stage where failure occurred.
     */
    public function getStageName(): string
    {
        return $this->stageName;
    }

    /**
     * Get stage-specific errors.
     *
     * @return array<string, string>
     */
    public function getStageErrors(): array
    {
        return $this->stageErrors;
    }
}
