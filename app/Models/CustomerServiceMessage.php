<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerServiceMessage extends Model
{
    protected $table = 'customer_service_messages';

    protected $fillable = [
        'session_id',
        'sender_id',
        'sender_type',
        'content',
        'message_type',
        'msg_type',
        'is_auto_reply',
        'file_url',
        'file_name',
        'file_path',
        'file_mime',
        'file_size',
        'thumbnail_url',
        'link_url',
        'link_title',
        'status',
        'read_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'read_at'   => 'datetime',
        'is_auto_reply' => 'boolean',
    ];

    /**
     * 兼容前端 msg_type 字段名（数据库列实际叫 message_type）
     * 通过访问器在 JSON 输出时同时暴露 msg_type，避免前端大量修改
     */
    protected $appends = ['msg_type'];

    public function getMsgTypeAttribute(): string
    {
        return $this->message_type ?? 'text';
    }

    /**
     * msg_type 设置器：将前端的 msg_type 映射到数据库的 message_type 列
     */
    public function setMsgTypeAttribute($value): void
    {
        $this->attributes['message_type'] = $value;
    }

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
        return $this->message_type === 'image';
    }

    /**
     * 判断是否为文件消息
     */
    public function isFile(): bool
    {
        return $this->message_type === 'file';
    }
}
