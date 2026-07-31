<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPackage extends Model
{
    protected $fillable = [
        'name',
        'type',
        'times',
        'days',
        'price',
        'original_price',
        'is_recommend',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'times' => 'integer',
            'days' => 'integer',
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'is_recommend' => 'boolean',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
