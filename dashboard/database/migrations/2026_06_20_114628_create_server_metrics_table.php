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
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->decimal('cpu_percent', 5, 2)->default(0); // 0.00 - 100.00
            $table->bigInteger('ram_used_mb')->default(0);
            $table->bigInteger('ram_total_mb')->default(0);
            $table->decimal('disk_used_gb', 10, 2)->default(0);
            $table->decimal('disk_total_gb', 10, 2)->default(0);
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['server_id', 'collected_at']);
            $table->index('collected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
