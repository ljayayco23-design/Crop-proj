<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Groq AI's OWN knowledge table (groq_treatment_records) — completely
 * separate from treatment_records (the admin-curated knowledge base).
 *
 * Rows are versioned: every save inserts a new row, and the newest row for a
 * given (disease, language) pair is the "main" info that gets reused whenever
 * Groq detects that same class again in that same dialect.
 */
class GroqTreatmentRecord extends Model
{
    protected $table = 'groq_treatment_records';

    protected $fillable = [
        'type',
        'disease',
        'language',
        'description',
        'treatments',
        'causes',
        'nutrient_deficiency',
        'grain_damage',   // for pests this holds the "damage symptoms"
        'natural_enemies',
        'prevention',
        'updated_by',
    ];
}