<?php

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
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->decimal('network_sent_mbps', 10, 2)->default(0)->after('disk_total_gb');
            $table->decimal('network_recv_mbps', 10, 2)->default(0)->after('network_sent_mbps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_metrics', function (Blueprint $table) {
            $table->dropColumn(['network_sent_mbps', 'network_recv_mbps']);
        });
    }
};
