<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerServiceSession extends Model
{
    protected $fillable = [
        'session_no',
        'user_id',
        'admin_id',
        'title',
        'status',
        'welcome_sent',
        'message_count',
        'user_unread',
        'admin_unread',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'welcome_sent' => 'boolean',
        'message_count' => 'integer',
        'user_unread' => 'integer',
        'admin_unread' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * 欢迎消息内容
     */
    public const WELCOME_MESSAGE = '您好！欢迎使用AI中医健康平台，我是您的专属客服。请问有什么可以帮助您的吗？您可以咨询健康问题、了解套餐服务或反馈使用体验。';

    /**
     * 发送欢迎消息（读取后台配置：欢迎内容 + 自动欢迎开关）
     */
    public function sendWelcomeMessage(): bool
    {
        if ($this->welcome_sent) {
            return false;
        }

        // 自动欢迎开关（后台配置，默认开启）
        $autoWelcome = CustomerServiceConfig::getValue('auto_welcome', 'true');
        if ($autoWelcome === 'false') {
            return false;
        }

        // 欢迎内容（后台配置，未配置时用默认文案）
        $content = CustomerServiceConfig::getValue('welcome_message', self::WELCOME_MESSAGE);

        $message = $this->messages()->create([
            'sender_id' => 0, // 系统发送
            'sender_type' => 'system',
            'content' => $content,
            'message_type' => 'text',
        ]);

        $this->update(['welcome_sent' => true]);

        return true;
    }

    /**
     * 生成唯一会话编号
     */
    public static function generateSessionNo(): string
    {
        do {
            $sessionNo = 'CS' . date('Ymd') . strtoupper(Str::random(6));
        } while (self::where('session_no', $sessionNo)->exists());
        
        return $sessionNo;
    }

    /**
     * 用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 客服管理员
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * 消息列表
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerServiceMessage::class, 'session_id');
    }

    /**
     * 获取最后一条消息
     */
    public function lastMessage()
    {
        return $this->messages()->latest()->first();
    }
}
