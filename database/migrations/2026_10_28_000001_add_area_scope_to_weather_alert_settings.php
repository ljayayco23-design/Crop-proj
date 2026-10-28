<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One weather_alert_settings row per scope:
 *   province_id + city_id NULL     -> global row (developer)
 *   province_id + city_id filled   -> one row per normal admin's area
 * The existing row keeps NULL/NULL, so it stays the developer's global row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weather_alert_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('province_id')->nullable()->after('id');
            $table->unsignedBigInteger('city_id')->nullable()->after('province_id');
            $table->index(['province_id', 'city_id'], 'was_area_idx');
        });
    }

    public function down(): void
    {
        Schema::table('weather_alert_settings', function (Blueprint $table) {
            $table->dropIndex('was_area_idx');
            $table->dropColumn(['province_id', 'city_id']);
        });
    }
};