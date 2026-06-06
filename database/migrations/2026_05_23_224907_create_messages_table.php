<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id');
            
            // to_user_id will be 0 when it's a Global Group Chat message
            $table->unsignedBigInteger('to_user_id')->default(0); 
            
            $table->text('message');
            $table->timestamps(); // Creates created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};