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
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->timestamp('bounced_at')->nullable()->after('failed_at');
            $table->timestamp('complained_at')->nullable()->after('bounced_at');
            $table->timestamp('unsubscribed_at')->nullable()->after('complained_at');
        });

        Schema::table('email_events', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('email_message_id')->constrained('contacts')->nullOnDelete();
            $table->foreignId('email_campaign_id')->nullable()->after('contact_id')->constrained('email_campaigns')->nullOnDelete();
            $table->text('link_url')->nullable()->after('user_agent');
            $table->string('bounce_type', 32)->nullable()->after('link_url');
            $table->string('bounce_subtype', 64)->nullable()->after('bounce_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_events', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['email_campaign_id']);
            $table->dropColumn([
                'contact_id',
                'email_campaign_id',
                'link_url',
                'bounce_type',
                'bounce_subtype',
            ]);
        });

        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn([
                'bounced_at',
                'complained_at',
                'unsubscribed_at',
            ]);
        });
    }
};
