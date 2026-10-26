<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action');
            $table->string('table_name');
            $table->uuid('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('performed_by');
            $table->string('performed_by_role')->default('admin');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('table_name');
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
