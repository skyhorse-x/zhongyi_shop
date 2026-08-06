<?php

namespace App\Support;

use App\Models\InviteClick;
use App\Models\InviteRegistration;
use App\Models\Promoter;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 推广链接客户端信息采集 + 反作弊检测
 *
 * 功能：
 * 1. 解析 User-Agent 获取设备/浏览器/OS
 * 2. 检测同 IP 重复点击/注册
 * 3. 识别机器人 UA
 * 4. 风险评分 + 作弊标记
 */
final class InviteTracker
{
    /**
     * 解析客户端信息（从 Request）
     */
    public static function parse(Request $request): array
    {
        $ua = (string) $request->header('User-Agent', '');
        $uaLower = strtolower($ua);

        // 设备类型
        $deviceType = 'desktop';
        if (preg_match('/(iphone|ipad|ipod|android.*mobile|windows phone)/i', $ua)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/(ipad|android(?!.*mobile))/i', $ua)) {
            $deviceType = 'tablet';
        }

        // 设备型号
        $deviceModel = '';
        if (preg_match('/\(([^)]+)\)/', $ua, $m)) {
            $deviceModel = trim($m[1]);
            if (strlen($deviceModel) > 100) {
                $deviceModel = substr($deviceModel, 0, 100);
            }
        }

        // 浏览器
        $browser = 'unknown';
        if (preg_match('/(edg|chrome|safari|firefox|opr|opera|msie|trident)[ \/]?(\d+)?/i', $ua, $m)) {
            $browser = strtolower($m[1]);
            if ($browser === 'edg') $browser = 'Edge';
            elseif ($browser === 'opr' || $browser === 'opera') $browser = 'Opera';
            elseif ($browser === 'msie' || $browser === 'trident') $browser = 'IE';
            elseif ($browser === 'chrome') $browser = 'Chrome';
            elseif ($browser === 'safari') $browser = 'Safari';
            elseif ($browser === 'firefox') $browser = 'Firefox';
        }

        // 操作系统
        $os = 'unknown';
        if (preg_match('/(windows nt|macintosh|mac os|linux|android|ios|iphone|ipad)/i', $ua, $m)) {
            $os = strtolower($m[1]);
            if (str_contains($os, 'windows')) $os = 'Windows';
            elseif (str_contains($os, 'mac')) $os = 'macOS';
            elseif (str_contains($os, 'linux')) $os = 'Linux';
            elseif (str_contains($os, 'android')) $os = 'Android';
            elseif (str_contains($os, 'ios') || str_contains($os, 'iphone') || str_contains($os, 'ipad')) $os = 'iOS';
        }

        // 简易指纹（UA + 语言 + 时区，不依赖 JS）
        $lang = (string) $request->header('Accept-Language', '');
        $fingerprint = hash('sha256', $ua . '|' . $lang);

        return [
            'ip'            => $request->ip(),
            'user_agent'    => $ua,
            'device_type'   => $deviceType,
            'device_model'  => $deviceModel,
            'browser'       => $browser,
            'os'            => $os,
            'fingerprint'   => $fingerprint,
        ];
    }

    /**
     * 检测 UA 是否可疑（机器人/爬虫）
     */
    public static function isBotUa(string $ua): bool
    {
        $botKeywords = (array) self::getConfig('bot_keywords', ['bot', 'crawler', 'spider', 'curl', 'wget', 'python', 'java']);
        $uaLower = strtolower($ua);
        foreach ($botKeywords as $kw) {
            if (str_contains($uaLower, strtolower($kw))) {
                return true;
            }
        }
        // UA 过短也可疑
        if (strlen($ua) < (int) self::getConfig('min_ua_length', 10)) {
            return true;
        }
        return false;
    }

    /**
     * 记录推广链接点击（访问时调用）
     *
     * @param User $inviter 邀请人（任意用户）
     * @return array [click 模型, 是否重复IP]
     */
    public static function recordClick(User $inviter, Request $request): array
    {
        $info = self::parse($request);
        $inviteCode = $inviter->invite_code;

        if (!$inviteCode) {
            return [null, false];
        }

        // 查找推广员记录（如果有）
        $promoter = Promoter::where('user_id', $inviter->id)->first();

        // 同 IP + 同邀请码：24 小时内算重复
        $existingClick = InviteClick::where('ip', $info['ip'])
            ->where('invite_code', $inviteCode)
            ->where('clicked_at', '>=', now()->subDay())
            ->exists();

        $isSuspicious = self::isBotUa($info['user_agent']);

        $click = InviteClick::create(array_merge($info, [
            'inviter_user_id' => $inviter->id,
            'promoter_id'     => $promoter?->id,
            'invite_code'     => $inviteCode,
            'is_duplicate_ip' => $existingClick,
            'is_suspicious'   => $isSuspicious,
            'clicked_at'      => now(),
        ]));

        return [$click, $existingClick];
    }

    /**
     * 记录邀请注册，并做反作弊校验
     * 如果邀请人还不是推广员，自动创建推广员记录
     *
     * @param User $inviter 邀请人（任意用户）
     * @param User $user 被邀请注册的用户
     * @return InviteRegistration|null
     */
    public static function recordRegistration(User $inviter, User $user, Request $request): ?InviteRegistration
    {
        $info = self::parse($request);

        // 查找推广员记录（如果有），如果没有则自动创建
        $promoter = Promoter::where('user_id', $inviter->id)->first();
        if (!$promoter) {
            $commissionRate = (float) (SystemConfig::getValue('commission_rate', 15));
            $promoter = Promoter::create([
                'user_id' => $inviter->id,
                'invite_code' => self::generatePromoterInviteCode(),
                'level' => 1,
                'commission_rate' => $commissionRate,
                'status' => 1,
                'activated_at' => now(),
            ]);
            $inviter->update(['is_promoter' => 1]);
        }

        // 风险评分
        $riskScore = self::calculateRiskScore($info, $inviter);
        $threshold = (int) self::getConfig('risk_score_threshold', 60);
        $isFraud = $riskScore >= $threshold;

        // 作弊原因
        $fraudReason = '';
        if ($isFraud) {
            $reasons = [];
            if (self::isBotUa($info['user_agent'])) $reasons[] = '机器人 UA';
            if ($info['ip'] === $inviter->last_login_ip) $reasons[] = '邀请人自身 IP 注册';
            if (self::isDuplicateIpToday($info['ip'], $inviter->invite_code)) {
                $reasons[] = '同 IP 多次注册';
            }
            $fraudReason = implode('、', $reasons) ?: '风险分过高';
        }

        $registration = InviteRegistration::create(array_merge($info, [
            'inviter_user_id' => $inviter->id,
            'promoter_id'     => $promoter->id,
            'user_id'         => $user->id,
            'invite_code'     => $inviter->invite_code,
            'is_fraud'        => $isFraud,
            'fraud_reason'    => $fraudReason,
            'risk_score'      => $riskScore,
        ]));

        // 更新推广员作弊计数
        if ($isFraud) {
            $promoter->increment('fraud_count');
            if ($promoter->fraud_count >= 10 && !$promoter->is_banned) {
                $promoter->update(['is_banned' => true, 'banned_at' => now()]);
            }
        }

        return $registration;
    }

    /**
     * 生成推广员专用邀请码（8位）
     */
    private static function generatePromoterInviteCode(): string
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(8));
        } while (Promoter::where('invite_code', $code)->exists());
        return $code;
    }

    /**
     * 风险评分
     */
    private static function calculateRiskScore(array $info, User $inviter): int
    {
        $score = 0;

        // 机器人 UA +30
        if (self::isBotUa($info['user_agent'])) {
            $score += 30;
        }

        // 同 IP 同邀请码之前有注册 +25
        if (self::isDuplicateIpToday($info['ip'], $inviter->invite_code)) {
            $score += 25;
        }

        // 邀请人自己注册 +40
        if ($info['ip'] === $inviter->last_login_ip) {
            $score += 40;
        }

        // 同一 IP 日注册数超过限制 +20
        $maxPerIp = (int) self::getConfig('max_per_ip_per_day', 5);
        $todayCount = InviteRegistration::where('ip', $info['ip'])
            ->whereDate('created_at', today())
            ->count();
        if ($todayCount >= $maxPerIp) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * 同 IP + 同邀请码今日已有注册
     */
    private static function isDuplicateIpToday(string $ip, ?string $inviteCode): bool
    {
        if (!$inviteCode) return false;
        return InviteRegistration::where('ip', $ip)
            ->where('invite_code', $inviteCode)
            ->whereDate('created_at', today())
            ->exists();
    }

    /**
     * 取反作弊配置
     */
    private static function getConfig(string $key, $default = null)
    {
        $raw = SystemConfig::getValue('invite_fraud_rules', '{}');
        $rules = json_decode($raw, true) ?: [];
        return $rules[$key] ?? $default;
    }
}
