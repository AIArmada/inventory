<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tablePrefix = config('filament-authz.database.table_prefix', '');

        Schema::create($tablePrefix . 'authz_permission_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->json('state');
            $table->string('hash', 64);
            $table->timestamps();

            $table->index('created_by');
            $table->index('created_at');
        });

        Schema::create($tablePrefix . 'authz_permission_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requester_id');
            $table->foreignUuid('approver_id')->nullable();
            $table->json('requested_permissions')->nullable();
            $table->json('requested_roles')->nullable();
            $table->text('justification')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('approver_note')->nullable();
            $table->text('denial_reason')->nullable();
            $table->timestamps();

            $table->index('requester_id');
            $table->index('approver_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create($tablePrefix . 'authz_delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delegator_id');
            $table->foreignUuid('delegatee_id');
            $table->string('permission');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('can_redelegate')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('delegator_id');
            $table->index('delegatee_id');
            $table->index('permission');
            $table->index(['delegatee_id', 'permission', 'revoked_at']);
        });
    }

    public function down(): void
    {
        $tablePrefix = config('filament-authz.database.table_prefix', '');

        Schema::dropIfExists($tablePrefix . 'authz_delegations');
        Schema::dropIfExists($tablePrefix . 'authz_permission_requests');
        Schema::dropIfExists($tablePrefix . 'authz_permission_snapshots');
    }
};
