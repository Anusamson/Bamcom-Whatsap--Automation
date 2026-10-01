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
        Schema::table('sequence_steps', function (Blueprint $table) {
            $table->foreignId('email_template_id')->nullable()->after('sequence_id')->constrained('email_templates')->nullOnDelete();
            $table->string('delay_unit')->default('minutes')->after('delay_minutes'); // minutes, hours, days, weeks
            $table->unsignedInteger('delay_value')->nullable()->after('delay_unit');
            $table->json('email_config')->nullable()->after('whatsapp_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sequence_steps', function (Blueprint $table) {
            $table->dropForeign(['email_template_id']);
            $table->dropColumn(['email_template_id', 'delay_unit', 'delay_value', 'email_config']);
        });
    }
};
