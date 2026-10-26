<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherAlert extends Model
{
    protected $table = 'weather_alerts';
    protected $guarded = [];

    protected $casts = [
        'fields'     => 'array',
        'issued_at'  => 'datetime',
        'expires_at' => 'datetime',
        'sent_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}