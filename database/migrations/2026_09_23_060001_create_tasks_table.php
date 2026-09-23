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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->cascadeOnDelete();

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete();

            $table->foreignId('deal_id')
                ->nullable()
                ->constrained('deals')
                ->nullOnDelete();

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('due_at')->index();
            $table->dateTime('completed_at')->nullable()->index();

            $table->string('priority', 20)->default('medium')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->string('type', 30)->default('follow_up')->index();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for fast tab & category filtering
            $table->index(['assigned_user_id', 'status', 'due_at']);
            $table->index(['contact_id', 'status', 'due_at']);
            $table->index(['lead_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
