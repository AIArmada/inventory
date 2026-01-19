<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('inventory.table_names.levels', 'inventory_levels');

        if (Schema::hasColumn($tableName, 'safety_stock')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            // Enhanced stock thresholds
            $table->integer('safety_stock')->nullable()->after('reorder_point');
            $table->integer('max_stock')->nullable()->after('safety_stock');

            // Decimal quantity support for fractional units
            $table->decimal('quantity_on_hand_decimal', 15, 4)->nullable()->after('quantity_on_hand');
            $table->decimal('quantity_reserved_decimal', 15, 4)->nullable()->after('quantity_reserved');

            // Alert tracking
            $table->string('alert_status')->nullable()->after('max_stock');
            $table->timestamp('last_alert_at')->nullable()->after('alert_status');
            $table->timestamp('last_stock_check_at')->nullable()->after('last_alert_at');

            // Unit of measure
            $table->string('unit_of_measure')->default('each')->after('last_stock_check_at');
            $table->decimal('unit_conversion_factor', 10, 4)->default(1)->after('unit_of_measure');

            // Lead time and supplier info
            $table->unsignedInteger('lead_time_days')->nullable()->after('unit_conversion_factor');
            $table->foreignUuid('preferred_supplier_id')->nullable()->after('lead_time_days');

            // Indexes
            $table->index('safety_stock');
            $table->index('max_stock');
            $table->index('alert_status');
            $table->index('last_alert_at');
            $table->index(['quantity_on_hand', 'safety_stock'], 'inventory_levels_stock_alert_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('inventory.table_names.levels', 'inventory_levels'), function (Blueprint $table): void {
            $table->dropIndex(['safety_stock']);
            $table->dropIndex(['max_stock']);
            $table->dropIndex(['alert_status']);
            $table->dropIndex(['last_alert_at']);
            $table->dropIndex('inventory_levels_stock_alert_idx');

            $table->dropColumn([
                'safety_stock',
                'max_stock',
                'quantity_on_hand_decimal',
                'quantity_reserved_decimal',
                'alert_status',
                'last_alert_at',
                'last_stock_check_at',
                'unit_of_measure',
                'unit_conversion_factor',
                'lead_time_days',
                'preferred_supplier_id',
            ]);
        });
    }
};
