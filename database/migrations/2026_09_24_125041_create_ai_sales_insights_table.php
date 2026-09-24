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
        Schema::create('ai_sales_insights', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('period')->default('30d')->index();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->longText('executive_summary');
            $table->json('metrics_snapshot');
            $table->json('insights');
            $table->json('recommendations')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('model_used')->default('gemini-1.5-pro');
            $table->unsignedInteger('tokens_used')->default(0);
            $table->float('duration_ms', 10, 2)->default(0.00);
            $table->timestamps();

            $table->index(['period', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_sales_insights');
    }
};
