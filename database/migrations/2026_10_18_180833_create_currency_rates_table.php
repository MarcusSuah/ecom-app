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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            // Null for rates fetched for every marketplace; set for a marketplace's manual override.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('base', 3);
            $table->char('quote', 3);
            // Units of quote per one unit of base, e.g. 1 USD = 1450.00 RWF.
            $table->decimal('rate', 18, 8);
            $table->string('source', 40);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'base', 'quote']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
