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
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('estate_name')->nullable();
            $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('scheduled')->index();
            $table->date('inspection_date')->index();
            $table->string('inspection_time', 50)->default('10:00 AM');
            $table->string('meeting_point', 500)->default('Bamcom Corporate Office, Plot 12, Admiralty Way, Lekki Phase 1, Lagos');
            $table->text('customer_notes')->nullable();
            $table->text('sales_notes')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['representative_id', 'inspection_date', 'inspection_time'], 'idx_inspections_rep_slot');
            $table->index(['inspection_date', 'status'], 'idx_inspections_date_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
