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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_uid')->unique(); // agt_01HXABCDEF
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('machine_id')->nullable();
            $table->string('agent_version')->nullable();
            $table->string('runtime_token_hash')->nullable(); // bcrypt hash
            $table->enum('status', ['inactive', 'online', 'offline', 'revoked'])->default('inactive');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            
            $table->index('agent_uid');
            $table->index('server_id');
            $table->index('status');
            $table->index('last_heartbeat_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
