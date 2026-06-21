<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't support adding enum values easily, so we use raw SQL
        DB::statement("ALTER TABLE service_commands MODIFY COLUMN action ENUM(
            'start_service',
            'stop_service',
            'restart_service',
            'enable_service',
            'disable_service',
            'get_service_status',
            'sync_services',
            'update'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE service_commands MODIFY COLUMN action ENUM(
            'start_service',
            'stop_service',
            'restart_service',
            'enable_service',
            'disable_service',
            'get_service_status',
            'sync_services'
        )");
    }
};
