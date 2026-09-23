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
        Schema::create('lead_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('event_key')->unique()->index();
            $table->string('category')->default('crm_event')->index();
            $table->integer('points')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('allow_multiple')->default(false);
            $table->unsignedInteger('cooldown_minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('scoring_rule_id')->nullable()->constrained('lead_scoring_rules')->nullOnDelete();
            $table->string('event_key')->index();
            $table->integer('points_awarded');
            $table->unsignedInteger('score_before');
            $table->unsignedInteger('score_after');
            $table->string('temperature_before');
            $table->string('temperature_after');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('system');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'event_key']);
            $table->index(['lead_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_score_logs');
        Schema::dropIfExists('lead_scoring_rules');
    }
};
