<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Local orders now; wholesale orders share this table from Phase 5.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_company_id')->constrained('companies')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('fulfilment', 20);
            $table->json('address_snapshot')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('currency', 3);
            $table->string('status', 30);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'buyer_id', 'created_at']);
            $table->index(['seller_company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
