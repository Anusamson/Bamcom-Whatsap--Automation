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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('sender_type')->default('contact')->index();
            $table->unsignedBigInteger('sender_id')->nullable()->index();
            $table->string('meta_message_id')->nullable()->unique()->index();
            $table->string('direction')->default('inbound')->index();
            $table->string('sender_phone')->nullable()->index();
            $table->string('recipient_phone')->nullable()->index();
            $table->string('type')->default('text')->index();
            $table->text('body')->nullable();
            $table->text('media_url')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->json('media_metadata')->nullable();
            $table->string('delivery_status')->default('received')->index();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['contact_id', 'created_at']);
            $table->index(['direction', 'delivery_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
