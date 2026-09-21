<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('address');
        $table->foreignId('province_id')->nullable()->after('lang')->constrained();
        $table->foreignId('city_id')->nullable()->after('province_id')->constrained();
        $table->foreignId('barangay_id')->nullable()->after('city_id')->constrained();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropConstrainedForeignId('province_id');
        $table->dropConstrainedForeignId('city_id');
        $table->dropConstrainedForeignId('barangay_id');
        $table->string('address')->nullable();
    });
}
};
