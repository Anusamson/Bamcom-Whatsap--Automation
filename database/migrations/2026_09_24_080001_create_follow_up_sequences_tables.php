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
        // 1. Follow-up sequences parent definition
        Schema::create('follow_up_sequences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('trigger_type')->default('manual')->index();
            $table->json('trigger_config')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('exit_on_deal_won')->default(true);
            $table->boolean('exit_on_lead_lost')->default(true);
            $table->boolean('exit_on_opt_out')->default(true);
            $table->boolean('exit_on_reply')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Individual steps in a sequence
        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sequence_id')->constrained('follow_up_sequences')->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('name')->nullable();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('delay_type')->default('minutes'); // minutes, hours, days
            $table->json('whatsapp_config')->nullable();
            $table->json('task_config')->nullable();
            $table->json('stage_change_config')->nullable();
            $table->json('tag_config')->nullable();
            $table->json('assignment_config')->nullable();
            $table->json('notification_config')->nullable();
            $table->json('applicability_rules')->nullable();
            $table->timestamps();

            $table->unique(['sequence_id', 'step_number']);
        });

        // 3. Contact & lead enrollments in sequences
        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sequence_id')->constrained('follow_up_sequences')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('status')->default('active')->index(); // active, paused, completed, cancelled
            $table->unsignedInteger('current_step_number')->default(0);
            $table->foreignId('next_step_id')->nullable()->constrained('sequence_steps')->nullOnDelete();
            $table->foreignId('last_executed_step_id')->nullable()->constrained('sequence_steps')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('next_step_due_at')->nullable();
            $table->timestamp('next_step_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('enrolled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('enrolled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sequence_id', 'contact_id', 'status']);
            $table->index(['contact_id', 'status']);
        });

        // 4. Execution audit log per step
        Schema::create('sequence_step_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_enrollment_id')->nullable()->constrained('sequence_enrollments')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('sequence_enrollments')->cascadeOnDelete();
            $table->foreignId('sequence_step_id')->nullable()->constrained('sequence_steps')->nullOnDelete();
            $table->unsignedInteger('step_number')->default(1);
            $table->string('status')->default('scheduled')->index(); // scheduled, completed, skipped, failed
            $table->json('verification_results')->nullable();
            $table->json('actions_summary')->nullable();
            $table->string('skip_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['sequence_enrollment_id', 'step_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequence_step_logs');
        Schema::dropIfExists('sequence_enrollments');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('follow_up_sequences');
    }
};
