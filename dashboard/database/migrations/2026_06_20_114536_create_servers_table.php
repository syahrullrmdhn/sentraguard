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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('hostname')->nullable();
            $table->enum('environment', ['production', 'staging', 'development', 'testing'])->default('production');
            $table->string('public_ip')->nullable();
            $table->string('private_ip')->nullable();
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->string('registration_token_hash')->nullable(); // bcrypt hash
            $table->timestamp('token_generated_at')->nullable();
            $table->boolean('token_used')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index('environment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
