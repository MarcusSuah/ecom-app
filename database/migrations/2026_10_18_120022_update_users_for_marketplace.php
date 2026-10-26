<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketplace sign-ups are active buyers straight away (the old approval
     * flow is gone), and platform admins are flagged on the account because
     * they work across every marketplace rather than inside one.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('approved')->change();
            $table->boolean('is_active')->default(true)->change();
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_platform_admin', 'phone', 'avatar']);
        });
    }
};
