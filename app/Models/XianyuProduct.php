<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XianyuProduct extends Model
{
    protected $fillable = [
        'title',
        'link',
        'amount',
        'times',
        'description',
        'sort_order',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'times' => 'integer',
            'sort_order' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
