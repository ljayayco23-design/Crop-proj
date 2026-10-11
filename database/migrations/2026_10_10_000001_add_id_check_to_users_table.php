<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stores the result of the ID photo check (face / text / quality hints) so the
// admin can see it next to the ID photo when approving a farmer.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'id_check')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('id_check')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'id_check')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('id_check');
            });
        }
    }
};