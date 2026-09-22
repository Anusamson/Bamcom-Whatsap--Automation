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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('lead_source')->default('whatsapp')->index();
            $table->string('status')->default('new')->index();
            $table->string('temperature')->default('warm')->index();
            $table->unsignedInteger('score')->default(50);
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->string('budget_range')->nullable();
            $table->string('purchase_timeline')->default('1_3_months');
            $table->string('preferred_location')->nullable();
            $table->string('property_interest')->nullable()->index();
            $table->string('qualification_status')->default('unqualified')->index();
            $table->text('notes')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for multifaceted filtering
            $table->index(['status', 'temperature']);
            $table->index(['assigned_user_id', 'status']);
            $table->index(['contact_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
