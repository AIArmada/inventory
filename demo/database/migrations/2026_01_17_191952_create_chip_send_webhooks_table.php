<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::create($tablePrefix . 'send_webhooks', function (Blueprint $table): void {
            // CHIP Send webhooks use integer identifiers instead of UUIDs
            $table->integer('id')->primary();

            $table->string('name');
            $table->text('public_key');
            $table->string('callback_url', 500);
            $table->string('email', 254);

            // List of subscribed event hooks as returned by the API
            $jsonType = (string) commerce_json_column_type('chip', 'json');
            $table->{$jsonType}('event_hooks');

            // API lifecycle timestamps (ISO8601 strings converted to timestamps)
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            // Owner scoping
            $table->nullableMorphs('owner');

            // Useful indexes for processing incoming webhook deliveries
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'send_webhooks');
    }
};
