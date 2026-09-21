<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a `source` column to user_detections so each individual scan can
     * record whether it was classified by Groq AI ('groq') or the on-device
     * model only ('model'). Nullable/defaulted so existing rows are
     * unaffected — the controller treats any existing NULL row as 'model'.
     */
    public function up(): void
    {
        Schema::table('user_detections', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->default('model')->after('confidence');
        });
    }

    public function down(): void
    {
        Schema::table('user_detections', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};