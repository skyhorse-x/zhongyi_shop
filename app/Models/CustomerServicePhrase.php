<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerServicePhrase extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'title',
        'content',
        'category',
        'sort_order',
        'is_public',
        'is_enabled',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
