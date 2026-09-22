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
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
            $table->string('meta_template_id')->nullable()->index();
            $table->string('name')->index();
            $table->string('category')->default('UTILITY')->index();
            $table->string('language', 15)->default('en_US')->index();
            $table->string('status')->default('APPROVED')->index();
            $table->string('header_type')->nullable();
            $table->text('header_content')->nullable();
            $table->text('body_text');
            $table->string('footer_text')->nullable();
            $table->json('buttons')->nullable();
            $table->json('components')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'language']);
            $table->index(['status', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
