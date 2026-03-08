<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum SerialStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Shipped = 'shipped';
    case Returned = 'returned';
    case InRepair = 'in_repair';
    case Disposed = 'disposed';
    case Lost = 'lost';
    case Recalled = 'recalled';

    /**
     * Get all options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Sold => 'Sold',
            self::Shipped => 'Shipped',
            self::Returned => 'Returned',
            self::InRepair => 'In Repair',
            self::Disposed => 'Disposed',
            self::Lost => 'Lost',
            self::Recalled => 'Recalled',
        };
    }

    /**
     * Get the badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Reserved => 'warning',
            self::Sold => 'info',
            self::Shipped => 'primary',
            self::Returned => 'warning',
            self::InRepair => 'gray',
            self::Disposed => 'danger',
            self::Lost => 'danger',
            self::Recalled => 'danger',
        };
    }

    /**
     * Check if serial can be allocated/sold.
     */
    public function isAllocatable(): bool
    {
        return $this === self::Available;
    }

    /**
     * Check if serial is in stock.
     */
    public function isInStock(): bool
    {
        return in_array($this, [self::Available, self::Reserved], true);
    }

    /**
     * Get allowed transitions from this status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Available => [self::Reserved, self::Sold, self::InRepair, self::Disposed, self::Lost],
            self::Reserved => [self::Available, self::Sold, self::Shipped],
            self::Sold => [self::Shipped, self::Returned],
            self::Shipped => [self::Returned],
            self::Returned => [self::Available, self::InRepair, self::Disposed],
            self::InRepair => [self::Available, self::Disposed],
            self::Disposed => [],
            self::Lost => [self::Available],
            self::Recalled => [self::Disposed, self::Available],
        };
    }

    /**
     * Check if transition to another status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }
}
