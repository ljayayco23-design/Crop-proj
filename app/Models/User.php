<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'province_id',
        'city_id',
        'barangay_id',
        'lang',
        // ADDED MISSING REGISTRATION FIELDS BELOW:
        'dob',
        'farmer_category',
        'farm_name',
        'latitude',
        'longitude',
        'device_latitude',
        'device_longitude',
        'farm_size',
        'water_source',
        'id_type',
        'document_photo',


        'field_id',
        'additional_farms',
        'rice_variety',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'address',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'additional_farms' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // 1. Delete standard treatment records
            \App\Models\TreatmentRecord::where('user_id', $user->id)->delete();
            
            // 2. Delete Groq treatment records 
            // \Illuminate\Support\Facades\DB::table('groq_treatment_records')->where('user_id', $user->id)->delete();
            
            // 3. Delete the user's scan/detection history
            \Illuminate\Support\Facades\DB::table('user_detections')->where('user_id', $user->id)->delete();
        });
    }

        public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function getAddressAttribute()
    {
        $parts = array_filter([
            $this->province->name ?? null,
            $this->city->name ?? null,
            $this->barangay->name ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }
}