<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreatmentRecord extends Model
{
    use HasFactory;

    protected $table = 'treatment_records';

    protected $fillable = [
        'disease',
        'treatments',
        'causes',
        'nutrient_deficiency',
        'grain_damage',
        'prevention',
        'type',
        'updated_by',
        'user_id'
    ];
}