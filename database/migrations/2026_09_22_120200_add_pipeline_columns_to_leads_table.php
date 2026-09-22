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
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('pipeline_id')->nullable()->after('contact_id')->constrained('pipelines')->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->after('pipeline_id')->constrained('pipeline_stages')->nullOnDelete();

            $table->index(['pipeline_id', 'pipeline_stage_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['pipeline_id', 'pipeline_stage_id']);
            $table->dropConstrainedForeignId('pipeline_stage_id');
            $table->dropConstrainedForeignId('pipeline_id');
        });
    }
};
