<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerServiceMessage extends Model
{
    protected $fillable = [
        'session_id',
        'sender_id',
        'sender_type',
        'content',
        'msg_type',
        'file_url',
        'file_name',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * 会话
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CustomerServiceSession::class, 'session_id');
    }

    /**
     * 判断是否为图片消息
     */
    public function isImage(): bool
    {
        return $this->msg_type === 'image';
    }

    /**
     * 判断是否为文件消息
     */
    public function isFile(): bool
    {
        return $this->msg_type === 'file';
    }
}
