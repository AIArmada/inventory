<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Jnt;

use AIArmada\Commerce\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function class_exists;

abstract class JntTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/jnt/database/migrations');

        if (class_exists(\Spatie\WebhookClient\Models\WebhookCall::class)) {
            Schema::dropIfExists('webhook_calls');

            Schema::create('webhook_calls', function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->string('name');
                $table->string('url', 512);
                $table->json('headers')->nullable();
                $table->json('payload')->nullable();
                $table->text('exception')->nullable();

                $table->timestamps();
            });
        }
    }
}
