<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConstitutionQuestion extends Model
{
    protected $fillable = [
        'category',
        'question',
        'type',
        'options',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
