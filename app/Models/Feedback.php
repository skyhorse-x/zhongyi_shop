<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'content', 'images',
        'contact', 'status', 'reply', 'replied_at', 'replied_by',
    ];

    protected $casts = [
        'images' => 'array',
        'replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replier()
    {
        return $this->belongsTo(Admin::class, 'replied_by');
    }
}
