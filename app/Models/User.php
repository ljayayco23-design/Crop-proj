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
        'address',
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}