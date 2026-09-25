<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors groq_treatment_records' shape exactly (same columns, same
     * usage pattern in KnowledgeController) so YOLO11n-sourced detections
     * get their own knowledge-base trail, independent of the shared
     * treatment_records table and of Groq's own table. Every insert here
     * is a new version/row (same "insert instead of update" pattern Groq
     * uses) so a full history can be shown later, same as
     * modifier.blade.php does for Groq's timeline.
     */
    public function up(): void
    {
        Schema::create('yolo11n_treatments_records', function (Blueprint $table) {
            $table->id();
            $table->string('type');                 // 'disease' | 'pest'
            $table->string('disease');               // class key, matches YOLO_CLASSES / diseaseNames|pestNames keys
            $table->text('description')->nullable();
            $table->text('treatments')->nullable();
            $table->text('causes')->nullable();
            $table->text('nutrient_deficiency')->nullable();
            $table->text('grain_damage')->nullable();
            $table->text('natural_enemies')->nullable();
            $table->text('prevention')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['disease', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yolo11n_treatments_records');
    }
};