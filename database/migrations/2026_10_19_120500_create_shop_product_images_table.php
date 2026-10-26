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
        Schema::create('shop_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumbnail_path');
            // 0 is the featured photo, also copied to shop_products.image.
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['shop_product_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_product_images');
    }
};
