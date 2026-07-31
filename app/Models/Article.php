<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'title',
        'cover',
        'summary',
        'content',
        'category',
        'views',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'status' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
