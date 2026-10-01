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
        // 1. Email Accounts
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider')->default('ses');
            $table->string('from_name');
            $table->string('from_email');
            $table->string('reply_to_email')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('daily_quota')->nullable();
            $table->unsignedInteger('sent_today')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Email Templates
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->longText('body_plain')->nullable();
            $table->string('category')->default('general')->index();
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Email Messages
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('email_account_id')->nullable()->constrained('email_accounts')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->string('to_email')->index();
            $table->string('to_name')->nullable();
            $table->string('from_email');
            $table->string('from_name');
            $table->string('reply_to_email')->nullable();
            $table->string('subject');
            $table->longText('body_html');
            $table->longText('body_plain')->nullable();
            $table->string('type', 32)->default('transactional')->index();
            $table->string('status', 32)->default('queued')->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_at', 'status']);
        });

        // 4. Email Events
        Schema::create('email_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained('email_messages')->cascadeOnDelete();
            $table->string('event_type', 32)->index();
            $table->string('provider_event_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        // 5. Email Suppressions
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('reason', 32)->default('manual')->index();
            $table->text('details')->nullable();
            $table->timestamp('suppressed_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Extend Contacts with Email Marketing Status and Consent Fields
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('email_marketing_status', 32)->default('pending_consent')->after('email')->index();
            $table->timestamp('email_consent_obtained_at')->nullable()->after('email_marketing_status');
            $table->string('email_consent_source')->nullable()->after('email_consent_obtained_at');
            $table->string('email_consent_ip', 45)->nullable()->after('email_consent_source');
            $table->timestamp('email_unsubscribed_at')->nullable()->after('email_consent_ip');
            $table->timestamp('email_bounced_at')->nullable()->after('email_unsubscribed_at');
            $table->unsignedInteger('email_bounce_count')->default(0)->after('email_bounced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'email_marketing_status',
                'email_consent_obtained_at',
                'email_consent_source',
                'email_consent_ip',
                'email_unsubscribed_at',
                'email_bounced_at',
                'email_bounce_count',
            ]);
        });

        Schema::dropIfExists('email_suppressions');
        Schema::dropIfExists('email_events');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_accounts');
    }
};
