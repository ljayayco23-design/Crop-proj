<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * IMPORTANT — change these before running in production, then change the
 * password again from wherever your app lets a user change their own
 * password (or re-run a tweaked copy of this migration). This is a
 * hardcoded, seeded-only login: there is no "create developer" button
 * anywhere in the UI on purpose.
 */
return new class extends Migration
{
    private const DEV_EMAIL    = 'developer@riceguard.local';
    private const DEV_PASSWORD = 'ChangeMe!DevPass123';

    public function up(): void
    {
        if (DB::table('users')->where('email', self::DEV_EMAIL)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'full_name'  => 'System Developer',
            'email'      => self::DEV_EMAIL,
            'password'   => Hash::make(self::DEV_PASSWORD),
            'role'       => 'developer',
            'status'     => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
            // If province_id / city_id / barangay_id are NOT NULL in your
            // users table, replace these with real IDs from your
            // provinces/cities/barangays tables before running this.
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', self::DEV_EMAIL)->delete();
    }
};