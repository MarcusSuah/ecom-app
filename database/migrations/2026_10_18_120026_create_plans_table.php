<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two audiences share this table: "marketplace" plans are sold by the
     * platform to tenants (tenant_id is null), "seller" plans are sold by a
     * tenant to the supplier and shop companies on its marketplace.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('audience', 20);
            $table->string('name');
            $table->string('slug');
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->string('currency', 3)->default('RWF');
            $table->string('interval', 10)->default('month');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('limits')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'audience', 'slug']);
            $table->index(['audience', 'tenant_id', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
