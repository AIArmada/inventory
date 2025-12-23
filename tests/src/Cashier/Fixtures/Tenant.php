<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Tenant extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
