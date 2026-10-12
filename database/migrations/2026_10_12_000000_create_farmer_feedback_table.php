<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback & Support: stores the farmer's "Suggest Improvement" and
 * "General Feedback" entries (one table, `type` says which).
 *
 * No foreign key on purpose (TiDB-friendly); ownership is enforced in
 * FarmerReportController by always scoping to user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('farmer_feedback')) {
            return;
        }

        Schema::create('farmer_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable()->unique();   // SG-001 / FB-001
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 20)->index();                // suggestion | general
            $table->string('category', 50)->nullable();         // suggestion: what to improve
            $table->unsignedTinyInteger('rating')->nullable();  // general: 1-5 stars
            $table->string('feature', 50)->nullable();          // general: which feature
            $table->text('message');
            $table->longText('screenshot')->nullable();         // optional data-URI image (suggestion)
            $table->boolean('allow_contact')->default(true);
            $table->string('status', 20)->default('submitted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_feedback');
    }
};  