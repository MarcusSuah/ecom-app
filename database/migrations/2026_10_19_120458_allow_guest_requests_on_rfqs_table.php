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
        Schema::table('rfqs', function (Blueprint $table) {
            // Guests post requests too; the account is linked once they sign in with the same email.
            $table->foreignId('buyer_id')->nullable()->change();
            $table->string('guest_name', 100)->nullable()->after('buyer_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone', 30)->nullable()->after('guest_email');
            // Lets a guest follow the request and its quotes from the link we email them.
            $table->string('access_token', 64)->nullable()->unique()->after('guest_phone');

            $table->index('guest_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropIndex(['guest_email']);
            $table->dropUnique(['access_token']);
            $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'access_token']);
        });
    }
};
