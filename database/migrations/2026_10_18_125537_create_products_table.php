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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('unit', 20);
            $table->unsignedInteger('moq');
            $table->unsignedSmallInteger('lead_time_min_days');
            $table->unsignedSmallInteger('lead_time_max_days');
            $table->string('currency', 3);
            $table->string('origin_country', 2)->default('RW');
            $table->boolean('sample_available')->default(false);
            $table->unsignedBigInteger('sample_price')->nullable();
            $table->string('status', 20)->default('draft');
            // Lowest tier price, kept in sync by SaveProduct, for filtering and sorting.
            $table->unsignedBigInteger('min_unit_price')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'category_id']);
            $table->index(['company_id', 'status']);
            $table->index(['tenant_id', 'status', 'min_unit_price']);
            $table->index(['tenant_id', 'status', 'moq']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
