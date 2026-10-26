<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos the buyer adds as proof when reporting a problem (paths on the
     * private disk).
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
