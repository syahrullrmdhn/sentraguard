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
        Schema::create('server_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('service_name')->index();
            $table->string('display_name')->nullable();
            $table->enum('status', ['Running', 'Stopped', 'Paused', 'Unknown'])->default('Unknown');
            $table->enum('startup_type', ['Automatic', 'Manual', 'Disabled', 'Unknown'])->default('Unknown');
            $table->boolean('is_allowed')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->unique(['server_id', 'service_name']);
            $table->index('is_allowed');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_services');
    }
};
