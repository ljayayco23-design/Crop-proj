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
        Schema::create('treatment_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Matches whereNull('user_id')
            $table->string('disease');
            $table->text('treatments')->nullable();
            $table->text('causes')->nullable();
            $table->text('nutrient_deficiency')->nullable();
            $table->text('grain_damage')->nullable();
            $table->text('prevention')->nullable();
            $table->timestamps();

            // Set up a foreign key index contextually if users exist
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_records');
    }
};