<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            // Owner. Plain indexed column (no FK) so this works whatever your users table looks like.
            $table->unsignedBigInteger('user_id')->index();

            // Created on the device (offline) so the same row can never be inserted twice by a re-sync.
            $table->uuid('uuid');

            $table->string('title', 150);
            $table->string('type', 20)->default('inspection');   // inspection|treatment|maintenance|followup|other
            $table->string('calendar', 10)->default('my');       // my|team
            $table->string('location', 150)->nullable();
            $table->string('technician', 120)->nullable();
            $table->string('status', 20)->default('approved');   // approved|pending|completed

            // Stored exactly as the farmer typed them (local wall-clock time, no timezone shifting).
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('all_day')->default(false);

            $table->string('color', 10)->nullable();             // optional override: blue|green|amber|purple|red|teal|gray
            $table->text('notes')->nullable();

            // Device clock (ms) of the last edit. Newest edit wins when two devices disagree.
            $table->unsignedBigInteger('client_updated_at')->default(0);

            $table->timestamps();
            $table->softDeletes(); // deleted rows stay as tombstones so an offline device can't bring them back

            $table->unique(['user_id', 'uuid']);
            $table->index(['user_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};