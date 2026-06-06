<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('treatment_records', function (Blueprint $table) {
        if (!Schema::hasColumn('treatment_records', 'type')) {
            $table->string('type')->nullable();
        }
        if (!Schema::hasColumn('treatment_records', 'updated_by')) {
            $table->string('updated_by')->nullable();
        }
        if (!Schema::hasColumn('treatment_records', 'nutrient_deficiency')) {
            $table->string('nutrient_deficiency')->nullable();
        }
        if (!Schema::hasColumn('treatment_records', 'grain_damage')) {
            $table->string('grain_damage')->nullable();
        }
        if (!Schema::hasColumn('treatment_records', 'prevention')) {
            $table->string('prevention')->nullable();
        }
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
