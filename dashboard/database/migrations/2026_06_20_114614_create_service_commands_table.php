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
        Schema::create('service_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('service_name')->index();
            $table->enum('action', [
                'start_service',
                'stop_service',
                'restart_service',
                'enable_service',
                'disable_service',
                'get_service_status',
                'sync_services'
            ]);
            $table->enum('status', [
                'pending',
                'picked',
                'running',
                'success',
                'failed',
                'timeout',
                'rejected',
                'cancelled'
            ])->default('pending');
            $table->integer('timeout_seconds')->default(60);
            $table->integer('exit_code')->nullable();
            $table->text('stdout')->nullable();
            $table->text('stderr')->nullable();
            $table->string('service_status_after')->nullable();
            $table->string('startup_type_after')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index(['server_id', 'status']);
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_commands');
    }
};
