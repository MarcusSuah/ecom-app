<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Delivery settings of a shop company. Fees are integers in the shop's
     * currency (RWF francs, USD cents); the per-km rate applies after the first km.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('address_text');
            $table->string('landmark')->nullable();
            $table->boolean('offers_pickup')->default(true);
            $table->boolean('offers_delivery')->default(true);
            $table->unsignedBigInteger('base_fee')->default(0);
            $table->unsignedBigInteger('per_km_rate')->default(0);
            $table->unsignedBigInteger('free_threshold')->nullable();
            $table->decimal('delivery_radius_km', 6, 2)->default(5);
            $table->json('opening_hours')->nullable();
            $table->string('currency', 3);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
