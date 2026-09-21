<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escalation to the admin's System Report page.
 *
 *  - escalated_at             set when a technician presses "Escalate to Admin"
 *                             (NULL = never escalated, so it never shows up
 *                             on the admin page)
 *  - admin_status             pending | open | in_progress | resolved
 *  - admin_status_updated_at  when the admin last changed it
 *
 * Kept separate from farmer_reports.status on purpose: that column is what
 * the farmer and technician see (pending / waiting_farmer / resolved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('farmer_reports', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable();
            }
            if (!Schema::hasColumn('farmer_reports', 'admin_status')) {
                $table->string('admin_status', 20)->nullable();
            }
            if (!Schema::hasColumn('farmer_reports', 'admin_status_updated_at')) {
                $table->timestamp('admin_status_updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('farmer_reports', function (Blueprint $table) {
            foreach (['escalated_at', 'admin_status', 'admin_status_updated_at'] as $col) {
                if (Schema::hasColumn('farmer_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};