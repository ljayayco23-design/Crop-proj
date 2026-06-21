<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentRecord extends Model
{
    use HasFactory;

    protected $table = 'treatment_records';

   protected $fillable = [
        'type',
        'user_id',
        'disease',
        'description', // <-- ADD THIS IF MISSING
        'treatments',
        'causes',
        'nutrient_deficiency',
        'grain_damage',
        'natural_enemies',
        'prevention',
        'updated_by'
    ];
}