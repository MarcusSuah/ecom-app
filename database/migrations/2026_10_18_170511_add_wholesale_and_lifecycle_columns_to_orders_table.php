<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wholesale orders made from an accepted quote point at it (one order
     * per quote). delivered_at starts the auto-release clock.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->unique()->after('seller_company_id')->constrained()->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->after('placed_at');
            $table->timestamp('completed_at')->nullable()->after('delivered_at');

            $table->index(['status', 'delivered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'delivered_at']);
            $table->dropConstrainedForeignId('quote_id');
            $table->dropColumn(['delivered_at', 'completed_at']);
        });
    }
};
