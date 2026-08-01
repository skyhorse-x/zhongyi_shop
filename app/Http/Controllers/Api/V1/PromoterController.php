<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\InviteClick;
use App\Models\InviteRegistration;
use App\Models\Promoter;
use App\Models\SystemConfig;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PromoterController extends Controller
{
    /**
     * 开通推广员（已自动开通，此方法保留为幂等兼容）
     */
    public function activate(Request $request)
    {
        $user = $request->user();

        // 检查是否已是推广员
        $promoter = Promoter::where('user_id', $user->id)->first();
        if ($promoter) {
            // 已是推广员，直接返回成功（幂等）
            return response()->json([
                'code' => 0,
                'message' => '您已是推广员',
                'data' => [
                    'invite_code' => $promoter->invite_code,
                    'invite_url' => Site::inviteLink($promoter->invite_code),
                    'level' => $promoter->level,
                    'commission_rate' => $promoter->commission_rate,
                    'activated_at' => $promoter->activated_at,
                ],
            ]);
        }

        // 兼容历史逻辑：极少数情况下推广员记录未自动创建
        $commissionRate = (float) (SystemConfig::where('key', 'commission_rate')->value('value') ?? 15);

        $promoter = Promoter::create([
            'user_id' => $user->id,
            'invite_code' => $this->generateInviteCode(),
            'level' => 1,
            'commission_rate' => $commissionRate,
            'status' => 1,
            'activated_at' => now(),
        ]);

        $user->update(['is_promoter' => 1]);

        return response()->json([
            'code' => 0,
            'message' => '开通成功',
            'data' => [
                'invite_code' => $promoter->invite_code,
                'invite_url' => Site::inviteLink($promoter->invite_code),
                'level' => $promoter->level,
                'commission_rate' => $promoter->commission_rate,
                'activated_at' => $promoter->activated_at,
            ],
        ]);
    }

    /**
     * 获取推广信息
     */
    public function info(Request $request)
    {
        $promoter = Promoter::where('user_id', $request->user()->id)->first();

        if (!$promoter) {
            return response()->json([
                'code' => 404,
                'message' => '您还不是推广员',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'invite_code' => $promoter->invite_code,
                'invite_url' => Site::inviteLink($promoter->invite_code),
                'level' => $promoter->level,
                'commission_rate' => $promoter->commission_rate,
                'total_invite' => $promoter->total_invite,
                'total_consume' => $promoter->total_consume,
                'total_commission' => $promoter->total_commission,
                'frozen_commission' => $promoter->frozen_commission,
                'withdrawn_commission' => $promoter->withdrawn_commission,
                'available_commission' => $promoter->total_commission - $promoter->frozen_commission - $promoter->withdrawn_commission,
            ],
        ]);
    }

    /**
     * 获取推广海报
     */
    public function poster(Request $request)
    {
        $promoter = Promoter::where('user_id', $request->user()->id)->first();

        if (!$promoter) {
            return response()->json([
                'code' => 404,
                'message' => '您还不是推广员',
            ], 404);
        }

        // 海报URL指向后端API动态生成（而非静态文件）
        $shareLink = Site::inviteLink($promoter->invite_code);
        $posterUrl = url('api/v1/promoter/poster-image?code=' . $promoter->invite_code);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'poster_url' => $posterUrl,
                'share_link' => $shareLink,
                'invite_code' => $promoter->invite_code,
            ],
        ]);
    }

    /**
     * 生成推广海报图片（返回真实 PNG 二进制）
     */
    public function posterImage(Request $request)
    {
        $code = $request->get('code');
        if (empty($code)) {
            return response()->json(['code' => 400, 'message' => '推广码不能为空'], 400);
        }

        $promoter = Promoter::where('invite_code', $code)->first();
        if (!$promoter) {
            return response()->json(['code' => 404, 'message' => '推广员不存在'], 404);
        }

        $shareLink = Site::inviteLink($promoter->invite_code);
        $nickname = $promoter->user->nickname ?? '推广员';

        // 已生成则直接返回（缓存到 public/posters/）
        $posterDir = storage_path('app/public/posters');
        $posterPath = $posterDir . '/' . $code . '.png';
        if (!is_dir($posterDir)) {
            @mkdir($posterDir, 0755, true);
        }
        if (file_exists($posterPath) && filesize($posterPath) > 1024) {
            return $this->respondPoster($posterPath);
        }

        // 生成海报
        try {
            $this->renderPoster($posterPath, $nickname, $promoter->invite_code, $shareLink);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Poster generation failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
            // 兜底：返回透明占位 PNG
            $this->writePlaceholderPng($posterPath);
        }

        return $this->respondPoster($posterPath);
    }

    /**
     * 输出 PNG（带缓存头）
     */
    private function respondPoster(string $absolutePath)
    {
        return response()->file($absolutePath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * 渲染推广海报（PHP GD）
     */
    private function renderPoster(string $savePath, string $nickname, string $inviteCode, string $shareLink): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension is required');
        }

        $width = 750;
        $height = 1334;

        $img = imagecreatetruecolor($width, $height);

        // 颜色定义
        $greenLight = imagecolorallocate($img, 0x07, 0xc1, 0x60);
        $greenDark  = imagecolorallocate($img, 0x04, 0xa1, 0x52);
        $white      = imagecolorallocate($img, 0xff, 0xff, 0xff);
        $textDark   = imagecolorallocate($img, 0x32, 0x32, 0x33);
        $textLight  = imagecolorallocate($img, 0x64, 0x65, 0x66);
        $bgCard     = imagecolorallocate($img, 0xff, 0xff, 0xff);
        $borderGray = imagecolorallocate($img, 0xea, 0xea, 0xea);
        $gold       = imagecolorallocate($img, 0xff, 0xb3, 0x00);

        // 1. 背景：垂直渐变
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int) (0x07 + (0x04 - 0x07) * $ratio);
            $g = (int) (0xc1 + (0xa1 - 0xc1) * $ratio);
            $b = (int) (0x60 + (0x52 - 0x60) * $ratio);
            $lineColor = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $lineColor);
        }

        // 2. 顶部装饰圆形
        imagefilledellipse($img, 80, 80, 200, 200, imagecolorallocatealpha($img, 0xff, 0xff, 0xff, 110));
        imagefilledellipse($img, $width - 100, 200, 240, 240, imagecolorallocatealpha($img, 0xff, 0xff, 0xff, 115));

        // 3. 加载中文字体
        $fontPath = $this->resolveFontPath();
        $hasFont = $fontPath !== null;

        // 4. 顶部 LOGO 文字
        if ($hasFont) {
            imagettftext($img, 56, 0, 60, 130, $white, $fontPath, '中医智能');
            imagettftext($img, 22, 0, 60, 170, $white, $fontPath, 'AI 中医体质分析平台');
        } else {
            imagestring($img, 5, 60, 100, 'TCM AI', $white);
        }

        // 5. 副标题
        if ($hasFont) {
            imagettftext($img, 32, 0, 60, 240, $white, $fontPath, '邀请您一起体验中医智能诊断');
        } else {
            imagestring($img, 4, 60, 220, 'Invite you to try TCM AI', $white);
        }

        // 6. 主卡片（白色圆角）
        $cardX = 60;
        $cardY = 320;
        $cardW = $width - 120;
        $cardH = 760;
        $this->drawRoundedRect($img, $cardX, $cardY, $cardW, $cardH, 24, $bgCard, $borderGray);

        // 7. 卡片标题
        if ($hasFont) {
            imagettftext($img, 36, 0, $cardX + 40, $cardY + 70, $textDark, $fontPath, '专属邀请');
            imagettftext($img, 22, 0, $cardX + 40, $cardY + 110, $textLight, $fontPath, '长按或扫描二维码加入');
        }

        // 8. 邀请人
        if ($hasFont) {
            imagettftext($img, 24, 0, $cardX + 40, $cardY + 170, $textLight, $fontPath, '邀请人：' . $this->safeText($nickname, 12));
        }

        // 9. 二维码区域
        $qrSize = 380;
        $qrX = $cardX + ($cardW - $qrSize) / 2;
        $qrY = $cardY + 210;
        $qrImg = $this->fetchQrCode($shareLink, $qrSize);
        if ($qrImg !== null) {
            imagecopy($img, $qrImg, (int) $qrX, (int) $qrY, 0, 0, $qrSize, $qrSize);
            imagedestroy($qrImg);
        } else {
            // 兜底：绘制提示框
            imagerectangle($img, (int) $qrX, (int) $qrY, (int) ($qrX + $qrSize), (int) ($qrY + $qrSize), $borderGray);
            if ($hasFont) {
                imagettftext($img, 20, 0, (int) ($qrX + 80), (int) ($qrY + 200), $textLight, $fontPath, '二维码生成中...');
            }
        }

        // 10. 邀请码（金色突出）
        if ($hasFont) {
            imagettftext($img, 22, 0, $cardX + 40, $cardY + $cardH - 130, $textLight, $fontPath, '邀请码');
            imagettftext($img, 56, 0, $cardX + 40, $cardY + $cardH - 65, $gold, $fontPath, $inviteCode);
        } else {
            imagestring($img, 5, $cardX + 40, $cardY + $cardH - 100, $inviteCode, $gold);
        }

        // 11. 底部品牌信息
        if ($hasFont) {
            imagettftext($img, 22, 0, 60, $height - 140, $white, $fontPath, '扫码 / 长按识别二维码');
            imagettftext($img, 18, 0, 60, $height - 100, $white, $fontPath, '中医智能 · 让健康更简单');
        }

        // 12. 底部装饰条
        imagefilledrectangle($img, 0, $height - 30, $width, $height, imagecolorallocate($img, 0xfa, 0xfa, 0xfa));

        imagepng($img, $savePath, 6);
        imagedestroy($img);
    }

    /**
     * 抓取远程二维码图片并返回 GD 资源
     */
    private function fetchQrCode(string $content, int $size = 380)
    {
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&margin=10&data=' . urlencode($content);

        try {
            $bin = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 6, 'method' => 'GET', 'header' => "User-Agent: PosterBot\r\n"],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]));
            if ($bin === false || strlen($bin) < 100) {
                return null;
            }
            $img = @imagecreatefromstring($bin);
            return $img ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 寻找系统中可用的中文字体
     */
    private function resolveFontPath(): ?string
    {
        $candidates = [
            'C:/Windows/Fonts/msyh.ttc',
            'C:/Windows/Fonts/msyh.ttf',
            'C:/Windows/Fonts/simhei.ttf',
            'C:/Windows/Fonts/simsun.ttc',
            '/System/Library/Fonts/PingFang.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-zenhei.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * 绘制圆角矩形
     */
    private function drawRoundedRect($img, int $x, int $y, int $w, int $h, int $r, $fill, $border): void
    {
        // 主体
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $fill);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $fill);
        // 四角
        imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $fill);
        imagefilledellipse($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, $fill);
        imagefilledellipse($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, $fill);
        imagefilledellipse($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $fill);

        // 边框
        imageline($img, $x + $r, $y, $x + $w - $r, $y, $border);
        imageline($img, $x + $r, $y + $h, $x + $w - $r, $y + $h, $border);
        imageline($img, $x, $y + $r, $x, $y + $h - $r, $border);
        imageline($img, $x + $w, $y + $r, $x + $w, $y + $h - $r, $border);
    }

    /**
     * 安全截断文本，避免超出宽度
     */
    private function safeText(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen) . '...';
    }

    /**
     * 兜底：写一张最小可用 PNG
     */
    private function writePlaceholderPng(string $path): void
    {
        $img = imagecreatetruecolor(750, 1334);
        $green = imagecolorallocate($img, 0x07, 0xc1, 0x60);
        imagefilledrectangle($img, 0, 0, 750, 1334, $green);
        imagepng($img, $path);
        imagedestroy($img);
    }

    /**
     * 获取佣金明细
     */
    public function commissions(Request $request)
    {
        $promoter = Promoter::where('user_id', $request->user()->id)->first();

        if (!$promoter) {
            return response()->json([
                'code' => 404,
                'message' => '您还不是推广员',
            ], 404);
        }

        $commissions = Commission::where('promoter_id', $promoter->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $commissions,
        ]);
    }

    /**
     * 获取提现记录
     */
    public function withdrawHistory(Request $request)
    {
        $promoter = Promoter::where('user_id', $request->user()->id)->first();

        if (!$promoter) {
            return response()->json([
                'code' => 404,
                'message' => '您还不是推广员',
            ], 404);
        }

        $withdraws = Withdraw::where('promoter_id', $promoter->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $withdraws,
        ]);
    }

    /**
     * 申请提现
     */
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'pay_type' => 'required|in:wechat,alipay',
            'pay_account' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $promoter = Promoter::where('user_id', $request->user()->id)->first();
        $available = $promoter->total_commission - $promoter->frozen_commission - $promoter->withdrawn_commission;

        if ($request->amount > $available) {
            return response()->json([
                'code' => 400,
                'message' => '可提现余额不足',
            ], 400);
        }

        $withdraw = Withdraw::create([
            'withdraw_no' => 'WD' . date('Ymd') . Str::random(8),
            'user_id' => $request->user()->id,
            'promoter_id' => $promoter->id,
            'amount' => $request->amount,
            'pay_type' => $request->pay_type,
            'pay_account' => $request->pay_account,
            'status' => 0,
        ]);

        // 冻结佣金
        $promoter->increment('frozen_commission', $request->amount);

        return response()->json([
            'code' => 0,
            'message' => '提现申请已提交',
            'data' => [
                'withdraw_no' => $withdraw->withdraw_no,
                'amount' => $withdraw->amount,
                'status' => $withdraw->status,
            ],
        ]);
    }

    /**
     * 推广链接被访问时记录点击（前端 JS 上报）
     * 也可由后端中间件自动调用
     */
    public function trackClick(Request $request)
    {
        $code = $request->input('code');
        if (empty($code)) {
            return response()->json(['code' => 400, 'message' => '邀请码不能为空'], 400);
        }

        $promoter = Promoter::where('invite_code', $code)->first();
        if (!$promoter) {
            return response()->json(['code' => 404, 'message' => '邀请码不存在'], 404);
        }

        if ($promoter->is_banned) {
            return response()->json(['code' => 403, 'message' => '推广员已被封禁'], 403);
        }

        [$click, $isDup] = \App\Support\InviteTracker::recordClick($promoter, $request);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'click_id'        => $click->id,
                'is_duplicate_ip' => $isDup,
            ],
        ]);
    }

    /**
     * 当前推广员的邀请记录（注册列表，含客户端信息）
     */
    public function inviteRecords(Request $request)
    {
        $promoter = Promoter::where('user_id', $request->user()->id)->first();
        if (!$promoter) {
            return response()->json(['code' => 404, 'message' => '您还不是推广员'], 404);
        }

        $records = InviteRegistration::with('user')
            ->where('promoter_id', $promoter->id)
            ->orderByDesc('created_at')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $records,
        ]);
    }

    /**
     * 当前推广员的邀请点击列表
     */
    public function inviteClicks(Request $request)
    {
        $promoter = Promoter::where('user_id', $request->user()->id)->first();
        if (!$promoter) {
            return response()->json(['code' => 404, 'message' => '您还不是推广员'], 404);
        }

        $clicks = InviteClick::where('promoter_id', $promoter->id)
            ->orderByDesc('clicked_at')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $clicks,
        ]);
    }

    /**
     * 后台：获取推广员邀请记录（含客户端信息、反作弊状态）
     */
    public function adminInviteRecords(Request $request)
    {
        $promoterId = $request->get('promoter_id');
        $query = InviteRegistration::with(['user', 'promoter']);

        if ($promoterId) {
            $query->where('promoter_id', $promoterId);
        }

        // 筛选
        if ($request->filled('is_fraud')) {
            $query->where('is_fraud', (bool) $request->input('is_fraud'));
        }
        if ($request->filled('device_type')) {
            $query->where('device_type', $request->input('device_type'));
        }
        if ($request->filled('ip')) {
            $query->where('ip', 'like', '%' . $request->input('ip') . '%');
        }
        if ($request->filled('date_start')) {
            $query->whereDate('created_at', '>=', $request->input('date_start'));
        }
        if ($request->filled('date_end')) {
            $query->whereDate('created_at', '<=', $request->input('date_end'));
        }

        $records = $query->orderByDesc('created_at')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $records,
        ]);
    }

    /**
     * 后台：封禁推广员
     */
    public function ban(Request $request, Promoter $promoter)
    {
        $promoter->update([
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '已封禁',
        ]);
    }

    /**
     * 后台：解封推广员
     */
    public function unban(Request $request, Promoter $promoter)
    {
        $promoter->update([
            'is_banned' => false,
            'banned_at' => null,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '已解封',
        ]);
    }

    /**
     * 生成唯一推广码
     */
    private function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Promoter::where('invite_code', $code)->exists());

        return $code;
    }
}
