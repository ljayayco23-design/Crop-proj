<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdminResolutionToFarmerReports extends Migration
{
    public function up()
    {
        Schema::table('farmer_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('farmer_reports', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
            if (!Schema::hasColumn('farmer_reports', 'assigned_technician_id')) {
                $table->unsignedBigInteger('assigned_technician_id')->nullable();
            }
            if (!Schema::hasColumn('farmer_reports', 'assigned_priority')) {
                $table->string('assigned_priority', 20)->nullable();
            }
            if (!Schema::hasColumn('farmer_reports', 'assigned_notes')) {
                $table->text('assigned_notes')->nullable();
            }
            if (!Schema::hasColumn('farmer_reports', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('farmer_reports', function (Blueprint $table) {
            $table->dropColumn(['admin_notes', 'assigned_technician_id', 'assigned_priority', 'assigned_notes', 'assigned_at']);
        });
    }
}