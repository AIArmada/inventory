<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_fraud_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cart_id')->index();
            $table->nullableUuidMorphs('owner');
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('signal_type')->index();
            $table->string('detector')->index();
            $table->unsignedSmallInteger('score')->index();
            $table->string('message');
            $jsonType = (string) commerce_json_column_type('cart', 'json');
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_fraud_signals');
    }
};
