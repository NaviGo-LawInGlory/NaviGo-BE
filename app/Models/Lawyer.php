<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Lawyer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'specialization',
        'location',
        'rating',
        'experience_years',
        'image_url',
        'email',
        'phone',
        'available',
    ];

    protected $casts = [
        'specialization' => 'array',
        'rating' => 'float',
        'experience_years' => 'integer',
        'available' => 'boolean',
    ];
}
