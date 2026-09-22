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
        Schema::create('estates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location');
            $table->string('city')->nullable();
            $table->string('state')->default('Lagos');
            $table->text('landmarks')->nullable();
            $table->string('title_document');
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('active'); // active, developing, sold_out
            $table->string('total_land_size')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estates');
    }
};
