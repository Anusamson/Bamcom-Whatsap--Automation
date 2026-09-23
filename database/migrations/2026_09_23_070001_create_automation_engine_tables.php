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
        // 1. automation_workflows
        Schema::create('automation_workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('allow_multiple_runs_per_subject')->default(true);
            $table->unsignedInteger('prevent_duplicate_window_seconds')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. automation_triggers
        Schema::create('automation_triggers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('trigger_type')->index();
            $table->json('filter_criteria')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 3. automation_conditions
        Schema::create('automation_conditions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('field');
            $table->string('operator');
            $table->json('value')->nullable();
            $table->string('logical_operator')->default('AND');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 4. automation_actions
        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->string('action_type')->index();
            $table->json('action_config');
            $table->unsignedInteger('delay_seconds')->default(0);
            $table->string('delay_type')->default('none');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 5. automation_runs
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workflow_id')->constrained('automation_workflows')->cascadeOnDelete();
            $table->foreignId('trigger_id')->nullable()->constrained('automation_triggers')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('idempotency_key')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('trigger_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'subject_type', 'subject_id'], 'idx_runs_workflow_subject');
            $table->index(['subject_type', 'subject_id'], 'idx_runs_subject');
        });

        // 6. automation_run_logs
        Schema::create('automation_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignId('action_id')->nullable()->constrained('automation_actions')->nullOnDelete();
            $table->string('action_type')->index();
            $table->string('status')->default('pending')->index();
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_run_logs');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_actions');
        Schema::dropIfExists('automation_conditions');
        Schema::dropIfExists('automation_triggers');
        Schema::dropIfExists('automation_workflows');
    }
};
