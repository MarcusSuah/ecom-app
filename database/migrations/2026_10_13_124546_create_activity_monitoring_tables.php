<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity monitoring: who did what, from which device / IP / location,
 * plus blocked IPs and blocked user accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activities')) {
            Schema::create('user_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();
                $table->string('email')->nullable();        // also set for failed logins (attempted email)
                $table->string('event', 40);                // login, logout, login_failed, page_view, create, update, delete, password_changed, blocked...
                $table->string('description', 500)->nullable();
                $table->string('method', 10)->nullable();
                $table->string('url', 500)->nullable();
                $table->string('route_name')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->string('device', 40)->nullable();   // Desktop / Mobile / Tablet / Bot
                $table->string('browser', 60)->nullable();
                $table->string('platform', 60)->nullable();
                $table->string('country', 80)->nullable();
                $table->string('region', 80)->nullable();
                $table->string('city', 80)->nullable();
                $table->boolean('is_suspicious')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->index(['user_id', 'created_at']);
                $table->index(['ip_address', 'created_at']);
                $table->index(['event', 'created_at']);
            });
        }

        if (! Schema::hasTable('blocked_ips')) {
            Schema::create('blocked_ips', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address', 45)->unique();
                $table->string('reason', 500)->nullable();
                $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('expires_at')->nullable();   // null = until unblocked
                $table->timestamps();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('is_active');
                $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('user_activities');
        Schema::table('users', function (Blueprint $table) {
            foreach (['blocked_at', 'blocked_reason', 'last_login_at', 'last_login_ip'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
