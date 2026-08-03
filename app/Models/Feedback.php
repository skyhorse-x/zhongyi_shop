<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    // feedback 是英语不可数名词，Laravel 默认不会复数化为 feedbacks
    // 数据库实际表名是 feedbacks（迁移创建时显式加了 s），这里显式指定对齐
    protected $table = 'feedbacks';

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
