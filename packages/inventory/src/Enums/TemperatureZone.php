<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum TemperatureZone: string
{
    case Ambient = 'ambient';
    case Chilled = 'chilled';
    case Frozen = 'frozen';
    case DeepFrozen = 'deep_frozen';
    case Controlled = 'controlled';
    case ClimateControlled = 'climate_controlled';

    /**
     * Get all options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $zone) {
            $options[$zone->value] = $zone->label();
        }

        return $options;
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ambient => 'Ambient (15-25°C)',
            self::Chilled => 'Chilled (2-8°C)',
            self::Frozen => 'Frozen (-18 to -22°C)',
            self::DeepFrozen => 'Deep Frozen (-25°C and below)',
            self::Controlled => 'Controlled Room Temp',
            self::ClimateControlled => 'Climate Controlled',
        };
    }

    /**
     * Get the minimum temperature in Celsius.
     */
    public function minTemperature(): float
    {
        return match ($this) {
            self::Ambient => 15.0,
            self::Chilled => 2.0,
            self::Frozen => -22.0,
            self::DeepFrozen => -40.0,
            self::Controlled => 15.0,
            self::ClimateControlled => 10.0,
        };
    }

    /**
     * Get the maximum temperature in Celsius.
     */
    public function maxTemperature(): float
    {
        return match ($this) {
            self::Ambient => 25.0,
            self::Chilled => 8.0,
            self::Frozen => -18.0,
            self::DeepFrozen => -25.0,
            self::Controlled => 25.0,
            self::ClimateControlled => 25.0,
        };
    }

    /**
     * Get the badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Ambient => 'gray',
            self::Chilled => 'info',
            self::Frozen => 'primary',
            self::DeepFrozen => 'danger',
            self::Controlled => 'warning',
            self::ClimateControlled => 'success',
        };
    }

    /**
     * Check if temperature zone is compatible with another.
     */
    public function isCompatibleWith(self $other): bool
    {
        // Same zone is always compatible
        if ($this === $other) {
            return true;
        }

        // Define compatibility rules
        return match ($this) {
            self::Ambient => in_array($other, [self::Controlled, self::ClimateControlled], true),
            self::Chilled => false,
            self::Frozen => $other === self::DeepFrozen,
            self::DeepFrozen => $other === self::Frozen,
            self::Controlled => in_array($other, [self::Ambient, self::ClimateControlled], true),
            self::ClimateControlled => in_array($other, [self::Ambient, self::Controlled], true),
        };
    }

    /**
     * Check if this zone can store temperature-sensitive items.
     */
    public function isTemperatureSensitive(): bool
    {
        return in_array($this, [self::Chilled, self::Frozen, self::DeepFrozen], true);
    }
}
