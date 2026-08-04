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
        'is_online',
        'last_active_at',
        'ip_address',
        'browser_info',
        'welcome_sent',
        'message_count',
        'user_unread',
        'admin_unread',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'is_online' => 'boolean',
        'welcome_sent' => 'boolean',
        'message_count' => 'integer',
        'user_unread' => 'integer',
        'admin_unread' => 'integer',
        'last_active_at' => 'datetime',
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

    /**
     * 更新用户在线状态
     */
    public function updateOnlineStatus(string $ipAddress, string $userAgent): void
    {
        $this->ip_address = $ipAddress;
        $this->browser_info = $userAgent;
        $this->is_online = true;
        $this->last_active_at = now();
        $this->save();
    }

    /**
     * 标记用户离线
     */
    public function markOffline(): void
    {
        $this->is_online = false;
        $this->save();
    }

    /**
     * 检查用户是否真正在线（5分钟内有活动才算在线）
     */
    public function getIsActuallyOnlineAttribute(): bool
    {
        if (!$this->is_online || !$this->last_active_at) {
            return false;
        }
        return $this->last_active_at->gt(now()->subMinutes(5));
    }

    /**
     * 获取浏览器简化信息
     */
    public function getBrowserShortAttribute(): string
    {
        if (!$this->browser_info) {
            return '未知';
        }

        $ua = $this->browser_info;
        $browser = '未知浏览器';

        if (strpos($ua, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($ua, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($ua, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) {
            $browser = 'IE';
        } elseif (strpos($ua, 'MicroMessenger') !== false) {
            $browser = '微信内置';
        } elseif (strpos($ua, 'QQ/') !== false) {
            $browser = 'QQ内置';
        }

        $os = '未知系统';
        if (strpos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($ua, 'Mac OS') !== false || strpos($ua, 'macOS') !== false) {
            $os = 'macOS';
        } elseif (strpos($ua, 'iPhone') !== false) {
            $os = 'iOS';
        } elseif (strpos($ua, 'iPad') !== false) {
            $os = 'iPadOS';
        } elseif (strpos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return "{$browser} / {$os}";
    }
}
