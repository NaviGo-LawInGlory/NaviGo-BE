<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Lawyer extends Model
{
    use HasFactory;

    // Jika nama tabel bukan plural 'lawyers', tambahkan:
    // protected $table = 'lawyers';

    // Kolom yang boleh diisi (mass assignable)
    protected $fillable = [
        'name',
        'min_price',
        'max_price',
        'rating',
        'location',
        'tag_haki',
        'tag_pajak',
        'profile_image',
    ];
}
