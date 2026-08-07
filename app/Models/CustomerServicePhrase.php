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
        'trigger_type',
        'keywords',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_enabled' => 'boolean',
        'is_auto_reply' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 获取关键词数组
     */
    public function getKeywordsArrayAttribute(): array
    {
        if (empty($this->keywords)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $this->keywords)));
    }

    /**
     * 是否为关键词触发
     */
    public function isKeywordTrigger(): bool
    {
        return $this->trigger_type === 'keyword';
    }

    /**
     * 是否为手动触发
     */
    public function isManualTrigger(): bool
    {
        return $this->trigger_type === 'manual';
    }
}
