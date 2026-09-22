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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('estate_id')->nullable()->constrained('estates')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('property_type')->default('land'); // land, residential, commercial, duplex, terrace, apartment
            $table->string('plot_size'); // e.g. 500sqm, 300sqm, 600sqm, 1 Acre
            $table->string('plot_number')->nullable();
            $table->string('location')->nullable();
            $table->string('title_document')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->string('availability')->default('available'); // available, reserved, under_offer, sold_out
            $table->integer('available_units')->default(1);
            $table->integer('total_units')->default(1);
            $table->string('status')->default('published'); // published, draft, archived
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estate_id', 'status']);
            $table->index(['availability', 'property_type']);
            $table->index('property_type');
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
