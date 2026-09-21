<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');    // admin | technician | farmer
            $table->string('module');  // e.g. 'user_management' (kept as a column so more
                                        // modules can be added later without a new table)
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->unique(['role', 'module']);
        });

        // Seed with the app's CURRENT behavior so flipping this feature on
        // doesn't silently lock anyone out. Today: admin & technician both
        // have full access to User Management, farmer has none (farmers
        // don't even have a panel that uses it).
        $now = now();
        DB::table('permissions')->insert([
            [
                'role' => 'admin', 'module' => 'user_management',
                'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'role' => 'technician', 'module' => 'user_management',
                'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'role' => 'farmer', 'module' => 'user_management',
                'can_view' => false, 'can_create' => false, 'can_edit' => false, 'can_delete' => false,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};