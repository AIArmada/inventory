<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Support;

use AIArmada\Inventory\Traits\HasInventory;
use AIArmada\Inventory\Traits\HasSerialNumbers;
use Illuminate\Database\Eloquent\Model;

/**
 * This class exists to ensure PHPStan analyses inventory traits.
 */
final class InventoryTraitsUsage extends Model
{
    use HasInventory;
    use HasSerialNumbers;
}
