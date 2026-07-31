<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'height',
        'weight',
        'blood_type',
        'medical_history',
        'allergies',
    ];

    protected function casts(): array
    {
        return [
            'medical_history' => 'array',
            'allergies' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
