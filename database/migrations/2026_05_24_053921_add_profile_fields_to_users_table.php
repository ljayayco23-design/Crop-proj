<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            
            // Check and add core fields (Shared by Admin, Tech, and Farmer)
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('farmer');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active');
            }
            if (!Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable();
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable();
            }
            
            // Check and add Farmer-specific fields
            if (!Schema::hasColumn('users', 'farm_size')) {
                $table->decimal('farm_size', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('users', 'preferred_variety')) {
                $table->string('preferred_variety')->nullable();
            }
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop the columns we are sure we added for profiles
            $columnsToDrop = [];
            
            if (Schema::hasColumn('users', 'profile_photo')) $columnsToDrop[] = 'profile_photo';
            if (Schema::hasColumn('users', 'phone')) $columnsToDrop[] = 'phone';
            if (Schema::hasColumn('users', 'address')) $columnsToDrop[] = 'address';
            if (Schema::hasColumn('users', 'farm_size')) $columnsToDrop[] = 'farm_size';
            if (Schema::hasColumn('users', 'preferred_variety')) $columnsToDrop[] = 'preferred_variety';
            if (Schema::hasColumn('users', 'bio')) $columnsToDrop[] = 'bio';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};