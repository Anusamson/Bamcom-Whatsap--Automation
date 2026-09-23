<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('contact_id')
                ->nullable()
                ->after('deal_id')
                ->constrained('contacts')
                ->cascadeOnDelete();

            $table->index(['contact_id', 'created_at']);
        });

        // Backfill contact_id for existing activities linked to leads
        try {
            DB::statement('
                UPDATE activities a
                INNER JOIN leads l ON a.lead_id = l.id
                SET a.contact_id = l.contact_id
                WHERE a.contact_id IS NULL AND a.lead_id IS NOT NULL
            ');
        } catch (Throwable $e) {
            // In testing environments or if empty, ignore statement failure
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['contact_id', 'created_at']);
            $table->dropColumn('contact_id');
        });
    }
};
