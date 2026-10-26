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
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 255)->unique()->nullable()->after('name');
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending')->after('email');
            $table->boolean('is_active')->default(false)->after('status');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()->after('is_active');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_verified')->default(false)->after('is_active');
            $table->foreignId('approved_by')->nullable()->after('is_verified')->constrained('users');
            $table->softDeletes();

            $table->index('status');
            $table->index('is_active');
            $table->index(['status', 'is_active']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('slug');
            $table->dropColumn('avatar');
            $table->dropColumn('is_active');
            $table->dropColumn('is_verified');
            $table->dropColumn('approved_by');
        });
    }
};
