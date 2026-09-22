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
        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
            $table->foreignId('estate_id')->nullable()->constrained('estates')->cascadeOnDelete();
            $table->string('media_type')->default('image'); // image, video, document, layout_plan, brochure
            $table->string('file_path');
            $table->string('file_url')->nullable();
            $table->string('caption')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('order_column')->default(0);
            $table->timestamps();

            $table->index(['property_id', 'is_primary']);
            $table->index(['estate_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_media');
    }
};
