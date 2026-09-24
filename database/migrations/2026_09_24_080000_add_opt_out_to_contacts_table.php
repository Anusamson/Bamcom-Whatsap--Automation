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
        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('has_opted_out')->default(false)->index()->after('status');
            $table->timestamp('opted_out_at')->nullable()->after('has_opted_out');
            $table->string('opt_out_reason')->nullable()->after('opted_out_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['has_opted_out', 'opted_out_at', 'opt_out_reason']);
        });
    }
};
