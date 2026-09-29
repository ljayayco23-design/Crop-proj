<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('groq_treatment_records')) {
            Schema::create('groq_treatment_records', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->nullable();
                $table->string('disease');
                $table->string('language', 20)->default('tagalog');
                $table->text('description')->nullable();
                $table->text('treatments')->nullable();
                $table->text('causes')->nullable();
                $table->text('nutrient_deficiency')->nullable();
                $table->text('grain_damage')->nullable();
                $table->text('natural_enemies')->nullable();
                $table->text('prevention')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->index(['disease', 'language']);
            });
            return;
        }

        if (!Schema::hasColumn('groq_treatment_records', 'language')) {
            Schema::table('groq_treatment_records', function (Blueprint $table) {
                // Existing rows had no dialect recorded; they become 'tagalog'
                // (the detection page's default dialect).
                $table->string('language', 20)->default('tagalog')->after('disease');
                $table->index(['disease', 'language']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('groq_treatment_records', 'language')) {
            Schema::table('groq_treatment_records', function (Blueprint $table) {
                $table->dropIndex(['disease', 'language']);
                $table->dropColumn('language');
            });
        }
    }
};