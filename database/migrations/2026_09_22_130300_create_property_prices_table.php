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
        Schema::create('property_prices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->decimal('regular_price', 14, 2);
            $table->decimal('promo_price', 14, 2)->nullable();
            $table->decimal('initial_deposit', 14, 2)->nullable();
            $table->text('payment_plan_summary')->nullable();
            $table->json('payment_plans')->nullable();
            $table->string('currency')->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['property_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_prices');
    }
};
