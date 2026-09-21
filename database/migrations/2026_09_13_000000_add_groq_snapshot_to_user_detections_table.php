<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a per-scan Groq snapshot column to `user_detections`.
     *
     * This is what makes Groq-classified scans independent of each other
     * and of the admin-curated `groq_treatment_records` table: each row
     * that was classified by Groq gets its OWN JSON blob of
     * description/treatments/causes/etc. (plus severity + is_pest) exactly
     * as returned for that photo. Model-classified rows leave this column
     * null and instead read from the shared fallback `treatment_records`
     * knowledge base, as they always did.
     */
    public function up(): void
    {
        Schema::table('user_detections', function (Blueprint $table) {
            if (!Schema::hasColumn('user_detections', 'groq_snapshot')) {
                $table->text('groq_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_detections', function (Blueprint $table) {
            if (Schema::hasColumn('user_detections', 'groq_snapshot')) {
                $table->dropColumn('groq_snapshot');
            }
        });
    }
};