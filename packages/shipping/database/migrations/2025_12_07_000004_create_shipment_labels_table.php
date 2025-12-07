<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();

            $table->string('format', 10); // pdf, png, zpl
            $table->string('size', 10)->nullable(); // a4, a6, 4x6
            $table->string('url')->nullable();
            $table->longText('content')->nullable(); // base64 encoded

            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['shipment_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_labels');
    }
};
