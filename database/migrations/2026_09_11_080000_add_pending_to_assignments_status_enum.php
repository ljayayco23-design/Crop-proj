<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes: SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status'
 *
 * The app has validated/accepted 'pending' as a status
 * (AdminAssignmentController@store and @update both allow
 * 'pending|active|ended') for a while, but the DB column itself is still
 * a MySQL ENUM that was only ever created with ('active','ended'). MySQL
 * silently truncates/rejects any value that isn't one of the enum's
 * declared members, which is exactly this error.
 *
 * Raw SQL is used instead of $table->enum('status', [...])->change()
 * because that requires doctrine/dbal, which many newer Laravel installs
 * don't have installed — this works either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `assignments` " .
            "MODIFY `status` ENUM('pending','active','ended') NOT NULL DEFAULT 'active'"
        );
    }

    public function down(): void
    {
        // Reverting would truncate any rows that are currently 'pending'.
        // Coerce them to 'active' first so the down-migration itself
        // doesn't throw the very same truncation error.
        DB::statement("UPDATE `assignments` SET `status` = 'active' WHERE `status` = 'pending'");

        DB::statement(
            "ALTER TABLE `assignments` " .
            "MODIFY `status` ENUM('active','ended') NOT NULL DEFAULT 'active'"
        );
    }
};