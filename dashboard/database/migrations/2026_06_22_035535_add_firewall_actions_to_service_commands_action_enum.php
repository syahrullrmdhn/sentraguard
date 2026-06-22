<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ALTER enum to add firewall actions
        DB::statement("ALTER TABLE service_commands MODIFY COLUMN action ENUM(
            'start_service',
            'stop_service',
            'restart_service',
            'enable_service',
            'disable_service',
            'get_service_status',
            'sync_services',
            'update',
            'firewall_add_rule',
            'firewall_enable_rule',
            'firewall_disable_rule',
            'firewall_delete_rule',
            'firewall_enable_all',
            'firewall_disable_all',
            'firewall_sync'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum
        DB::statement("ALTER TABLE service_commands MODIFY COLUMN action ENUM(
            'start_service',
            'stop_service',
            'restart_service',
            'enable_service',
            'disable_service',
            'get_service_status',
            'sync_services'
        ) NOT NULL");
    }
};
