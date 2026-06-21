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
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('rule_name');
            $table->string('direction')->default('inbound'); // inbound/outbound
            $table->string('protocol')->default('tcp'); // tcp/udp/any
            $table->string('port')->nullable(); // e.g., "80", "443", "3000-3010"
            $table->string('action')->default('allow'); // allow/block
            $table->boolean('is_enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
