<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin assignment + support (resolution) columns on farmer_feedback.
 *
 * Safe to re-run: every column is checked first, so if an earlier attempt
 * died half-way only the missing columns are added.
 *
 * ONE column per ALTER TABLE on purpose: TiDB (your database) can reject or
 * stall a single ALTER that adds several columns/indexes at once, which is
 * what a migration that prints "Running migrations." and never says DONE
 * looks like.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('farmer_feedback')) {
            return; // run 2026_10_12_000000_create_farmer_feedback_table first
        }

        $this->add('assigned_technician_id', fn (Blueprint $t) => $t->unsignedBigInteger('assigned_technician_id')->nullable());
        $this->add('assigned_at',            fn (Blueprint $t) => $t->timestamp('assigned_at')->nullable());
        $this->add('assigned_priority',      fn (Blueprint $t) => $t->string('assigned_priority', 20)->nullable());
        $this->add('assigned_notes',         fn (Blueprint $t) => $t->text('assigned_notes')->nullable());
        $this->add('support_status',         fn (Blueprint $t) => $t->string('support_status', 20)->nullable());
        $this->add('support_notes',          fn (Blueprint $t) => $t->text('support_notes')->nullable());
        $this->add('support_updated_at',     fn (Blueprint $t) => $t->timestamp('support_updated_at')->nullable());
        $this->add('support_by',             fn (Blueprint $t) => $t->string('support_by', 20)->nullable());
    }

    public function down(): void
    {
        foreach ([
            'assigned_technician_id', 'assigned_at', 'assigned_priority', 'assigned_notes',
            'support_status', 'support_notes', 'support_updated_at', 'support_by',
        ] as $col) {
            if (Schema::hasColumn('farmer_feedback', $col)) {
                Schema::table('farmer_feedback', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }

    private function add(string $column, \Closure $define): void
    {
        if (Schema::hasColumn('farmer_feedback', $column)) {
            return;
        }
        Schema::table('farmer_feedback', fn (Blueprint $t) => $define($t));
    }
};