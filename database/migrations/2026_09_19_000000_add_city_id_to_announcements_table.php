<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // Nullable on purpose: null means "posted by a developer /
            // no single city" and is treated as a global broadcast (see
            // AnnouncementController). Every admin-posted announcement
            // always gets a real city_id.
            $table->foreignId('city_id')->nullable()->after('role')
                  ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};