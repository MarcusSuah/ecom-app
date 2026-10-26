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
        Schema::table('audit_logs', function (Blueprint $table) {
            // Null for platform-level changes.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            // Records use integer keys here, not UUIDs.
            $table->string('record_id', 64)->nullable()->change();
            $table->string('performed_by_name')->nullable()->after('performed_by');

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('user_activities', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('performed_by_name');
        });
    }
};
