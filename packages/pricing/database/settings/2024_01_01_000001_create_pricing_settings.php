<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('pricing.defaultCurrency', 'MYR');
        $this->migrator->add('pricing.decimalPlaces', 2);
        $this->migrator->add('pricing.pricesIncludeTax', false);
        $this->migrator->add('pricing.roundingMode', 'half_up');
        $this->migrator->add('pricing.minimumOrderValue', 0);
        $this->migrator->add('pricing.maximumOrderValue', 100000_00); // 100,000.00
        $this->migrator->add('pricing.promotionalPricingEnabled', true);
        $this->migrator->add('pricing.tieredPricingEnabled', true);
        $this->migrator->add('pricing.customerGroupPricingEnabled', false);
    }
};
