<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();

            // The person being assigned (admin or technician). We snapshot
            // their role into user_type at assignment time so a later role
            // change on the User record doesn't silently rewrite history.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('user_type', ['admin', 'technician']);

            $table->foreignId('province_id')->constrained('provinces');
            $table->foreignId('city_id')->constrained('cities');
            $table->foreignId('barangay_id')->constrained('barangays');

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->enum('status', ['active', 'ended'])->default('active');

            // Who created the assignment (for audit trail).
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['province_id', 'city_id', 'barangay_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};