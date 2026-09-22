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
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('order_column')->default(0);
            $table->string('color')->default('blue');
            $table->integer('probability')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pipeline_id', 'order_column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
