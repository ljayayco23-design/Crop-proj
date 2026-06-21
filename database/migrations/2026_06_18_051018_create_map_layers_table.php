<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('map_layers', function (Blueprint $table) {
            $table->id();
            // This links the drawing to the specific user (Farmer)
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
            $table->string('layer_id');
            $table->string('type'); // Marker, Text, Polygon, etc.
            $table->json('geojson');
            $table->json('properties');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('map_layers');
    }
};