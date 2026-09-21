<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * database/migrations/2026_09_18_000000_create_farmer_reports_table.php
 *
 * One row = one problem a farmer raised about a detection result.
 *
 * The detection itself is SNAPSHOTTED here (class, confidence, severity,
 * image, and the knowledge-base text as the farmer actually saw it) instead
 * of being joined to user_detections/treatment_records. That's deliberate:
 * the knowledge base gets edited by admins and technicians over time, so a
 * live join would show the technician different text from what the farmer
 * complained about. The snapshot keeps the report honest.
 *
 * image_path follows the SAME convention as user_detections.image_path (see
 * FarmerHistoryController@saveDetection): it holds the full base64 data URI
 * string, so no filesystem/storage-link work is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_reports', function (Blueprint $table) {
            $table->id();

            // Human-facing ID shown in the UI (RP-001, RP-002, ...).
            $table->string('report_code')->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();       // farmer
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();

            // ---- Detection snapshot ----
            $table->string('class_key')->nullable();
            $table->string('class_name')->nullable();
            $table->unsignedSmallInteger('confidence')->default(0);
            $table->string('severity_label')->nullable();
            $table->unsignedSmallInteger('severity_percent')->default(0);
            $table->string('source')->nullable();                 // 'model' | 'groq'
            $table->longText('image_path')->nullable();           // base64 data URI
            $table->json('info')->nullable();                     // section key => text shown

            // ---- What the farmer reported ----
            $table->json('problem_types')->nullable();            // dropdown selections
            $table->json('flagged_sections')->nullable();         // section keys marked red
            $table->text('message');
            $table->string('suggested_class')->nullable();
            $table->longText('support_image_path')->nullable();   // optional extra photo

            // ---- Workflow ----
            // pending        -> no technician action yet
            // waiting_farmer -> technician asked for another image
            // resolved       -> technician reviewed and closed it
            $table->string('status')->default('pending');

            // ---- Technician review ----
            $table->string('assessment')->nullable();             // correct | incorrect | info_incorrect | need_image | cannot_determine
            $table->string('corrected_class')->nullable();
            $table->string('corrected_name')->nullable();
            $table->string('corrected_severity')->nullable();
            $table->json('corrected_sections')->nullable();       // info titles the technician corrected
            $table->text('notes')->nullable();
            $table->text('advice')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_reports');
    }
};