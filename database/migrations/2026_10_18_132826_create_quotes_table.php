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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->unsignedSmallInteger('lead_time_min_days');
            $table->unsignedSmallInteger('lead_time_max_days');
            $table->string('sample_terms', 500)->nullable();
            $table->date('valid_until');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->timestamps();

            // One quote per supplier per RFQ.
            $table->unique(['rfq_id', 'company_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
