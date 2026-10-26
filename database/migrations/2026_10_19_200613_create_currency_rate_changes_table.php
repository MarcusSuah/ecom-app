<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every change to an exchange rate (daily fetch, manual override or a
     * return to the daily rate), so reports can show which rate applied.
     */
    public function up(): void
    {
        Schema::create('currency_rate_changes', function (Blueprint $table) {
            $table->id();
            // Null for the shared daily rate; a marketplace for its own override.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('base', 3);
            $table->char('quote', 3);
            $table->decimal('old_rate', 20, 8)->nullable();
            $table->decimal('new_rate', 20, 8)->nullable();
            $table->string('source', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');

            $table->index(['tenant_id', 'quote', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rate_changes');
    }
};
