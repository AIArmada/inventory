<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('pricing_promotional.flashSalesEnabled', true);
        $this->migrator->add('pricing_promotional.defaultFlashSaleDurationHours', 24);
        $this->migrator->add('pricing_promotional.maxDiscountPercentage', 90);
        $this->migrator->add('pricing_promotional.allowPromotionStacking', false);
        $this->migrator->add('pricing_promotional.maxStackablePromotions', 2);
        $this->migrator->add('pricing_promotional.showOriginalPrice', true);
        $this->migrator->add('pricing_promotional.showCountdownTimers', true);
    }
};
