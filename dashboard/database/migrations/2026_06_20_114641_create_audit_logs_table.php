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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('server_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('actor_type')->default('user'); // user, agent, system
            $table->string('actor_identifier')->nullable(); // user email or agent_uid
            $table->string('action'); // login, logout, server.create, agent.register, service.start, etc
            $table->string('resource_type')->nullable(); // server, agent, service, command
            $table->string('resource_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // additional context data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('result', ['success', 'failed', 'error'])->default('success');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('user_id');
            $table->index('server_id');
            $table->index('action');
            $table->index(['actor_type', 'actor_identifier']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
