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
        'is_auto_reply',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_enabled' => 'boolean',
        'is_auto_reply' => 'boolean',
        'sort_order' => 'integer',
    ];
}
