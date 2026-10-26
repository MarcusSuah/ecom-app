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
        Schema::table('products', function (Blueprint $table) {
            // Units ready to ship; null means made to order (no limit).
            $table->unsignedInteger('stock')->nullable()->after('moq');
            // Units in paid orders, for the "sold" badge.
            $table->unsignedInteger('units_sold')->default(0)->after('stock');
        });

        Schema::table('shop_products', function (Blueprint $table) {
            $table->unsignedInteger('units_sold')->default(0)->after('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'units_sold']);
        });

        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropColumn('units_sold');
        });
    }
};
