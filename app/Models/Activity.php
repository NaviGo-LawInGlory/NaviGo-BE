<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Activity extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'document_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
