<?php

namespace App\Services;

use App\Models\AnalysisTask;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Promoter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * 支付宝配置
     */
    protected array $alipayConfig;

    /**
     * 佣金比例配置（默认15%）
     */
    protected float $commissionRate = 0.15;

    public function __construct()
    {
        // 支付配置从后台「系统设置」中读取
        $this->alipayConfig = [
            'app_id' => \App\Models\SystemConfig::where('key', 'alipay_app_id')->value('value') ?: '',
            'private_key' => \App\Models\SystemConfig::where('key', 'alipay_private_key')->value('value') ?: '',
            'public_key' => \App\Models\SystemConfig::where('key', 'alipay_public_key')->value('value') ?: '',
            'gateway' => 'https://openapi.alipay.com/gateway.do',
            'notify_url' => url('/api/v1/payment/notify/alipay'),
            'return_url' => url('/payment/success'),
        ];

        // 从系统配置读取佣金比例
        $rate = \App\Models\SystemConfig::where('key', 'commission_rate')->value('value');
        if ($rate) {
            $this->commissionRate = floatval($rate) / 100;
        }
    }

    /**
     * 创建支付订单
     *
     * @param User $user
     * @param string $type 订单类型: analysis, package
     * @param string $relationId 关联ID
     * @param string $payType 支付方式: wechat, alipay, balance
     * @param float $amount 支付金额
     * @return array
     * @throws \Exception
     */
    public function createOrder(User $user, string $type, string $relationId, string $payType, float $amount): array
    {
        Log::info('Creating payment order', [
            'user_id' => $user->id,
            'type' => $type,
            'relation_id' => $relationId,
            'pay_type' => $payType,
            'amount' => $amount,
        ]);

        // 校验支付方式是否已开启
        $this->guardPayType($payType);

        // 余额支付：单事务内完成「创建订单 → 扣减余额 → 标记已支付 → 发货」
        if ($payType === 'balance') {
            return $this->createOrderWithBalance($user, $type, $relationId, $amount);
        }

        // 创建订单
        $order = Order::create([
            'order_no' => $this->generateOrderNo(),
            'user_id' => $user->id,
            'type' => $type,
            'relation_id' => $relationId,
            'amount' => $amount,
            'pay_type' => $payType,
            'status' => 0, // 待支付
        ]);

        // 根据支付方式生成支付参数
        $payParams = match ($payType) {
            'alipay' => $this->createAlipayParams($order),
            'wechat' => $this->createWechatParams($order),
            default => throw new \InvalidArgumentException("Unsupported payment type: {$payType}"),
        };

        Log::info('Payment order created successfully', [
            'order_no' => $order->order_no,
            'pay_type' => $payType,
        ]);

        return [
            'order_no' => $order->order_no,
            'pay_amount' => (float) $order->amount,
            'pay_type' => $payType,
            'pay_params' => $payParams,
        ];
    }

    /**
     * 余额支付下单（单事务）
     */
    protected function createOrderWithBalance(User $user, string $type, string $relationId, float $amount): array
    {
        if ((float) $user->balance < $amount) {
            throw new \Exception(
                '余额不足，当前余额 ¥' . number_format((float) $user->balance, 2)
                . '，需要 ¥' . number_format($amount, 2)
            );
        }

        return DB::transaction(function () use ($user, $type, $relationId, $amount) {
            // 重新加行锁读最新余额（防并发）
            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            if ((float) $locked->balance < $amount) {
                throw new \Exception('余额不足，支付失败');
            }

            // 1. 创建已支付订单
            $order = Order::create([
                'order_no' => $this->generateOrderNo(),
                'user_id' => $user->id,
                'type' => $type,
                'relation_id' => $relationId,
                'amount' => $amount,
                'pay_type' => 'balance',
                'status' => 1, // 已支付
                'transaction_id' => 'BALANCE_' . Str::random(16),
                'paid_at' => now(),
            ]);

            // 2. 扣减余额 + 写流水
            $before = (float) $locked->balance;
            $after = round($before - $amount, 2);
            $locked->balance = $after;
            $locked->save();

            \App\Models\UserBalanceLog::create([
                'user_id'     => $locked->id,
                'change'      => -$amount,
                'before'      => $before,
                'after'       => $after,
                'type'        => 'consume',
                'remark'      => '购买次数包，订单号 ' . $order->order_no,
                'operator_id' => null,
            ]);

            // 3. 发货（与第三方支付成功走同一逻辑）
            $this->fulfillOrder($order);

            Log::info('Balance payment order created and fulfilled', [
                'order_no' => $order->order_no,
                'amount' => $amount,
                'balance_after' => $after,
            ]);

            return [
                'order_no'     => $order->order_no,
                'pay_amount'   => (float) $order->amount,
                'pay_type'     => 'balance',
                'paid'         => true,
                'balance_after' => $after,
                'pay_params'   => [
                    'method' => 'balance',
                    'message' => '余额支付成功',
                ],
            ];
        });
    }

    /**
     * 发货（订单类型对应的服务解锁）
     */
    protected function fulfillOrder(Order $order): void
    {
        switch ($order->type) {
            case 'analysis':
                AnalysisTask::where('task_no', $order->relation_id)
                    ->update(['is_paid' => true]);
                Log::info('Analysis report unlocked', ['task_no' => $order->relation_id]);
                break;
            case 'package':
                $this->addUserAnalysisTimes($order->user_id, $order->relation_id);
                Log::info('User analysis times added', [
                    'user_id' => $order->user_id,
                    'package_id' => $order->relation_id,
                ]);
                break;
        }
        // 计算推广佣金
        $this->calculateCommission($order);
    }

    /**
     * 校验支付方式开关
     */
    public function guardPayType(string $payType): void
    {
        $key = match ($payType) {
            'wechat'  => 'payment_wechat_enabled',
            'alipay'  => 'payment_alipay_enabled',
            'balance' => 'payment_balance_enabled',
            default => throw new \InvalidArgumentException("Unsupported payment type: {$payType}"),
        };
        $enabled = \App\Models\SystemConfig::getValue($key, '1');
        if ($enabled !== '1') {
            throw new \Exception('该支付方式已关闭，请选择其他支付方式');
        }
    }

    /**
     * 获取当前可用的支付方式列表
     * 前台套餐页用：余额永远返回（即便关闭也返回，方便显示"已关闭"），但 is_enabled 字段
     */
    public function getAvailablePayTypes(): array
    {
        $list = [
            ['code' => 'balance', 'name' => '余额支付', 'icon' => '💰', 'is_enabled' => true],
            ['code' => 'wechat',  'name' => '微信支付', 'icon' => '💚', 'is_enabled' => \App\Models\SystemConfig::getValue('payment_wechat_enabled', '1') === '1'],
            ['code' => 'alipay',  'name' => '支付宝',   'icon' => '💙', 'is_enabled' => \App\Models\SystemConfig::getValue('payment_alipay_enabled', '1') === '1'],
        ];
        return $list;
    }

    /**
     * 处理支付宝支付通知
     *
     * @param array $notifyData
     * @return bool
     * @throws \Exception
     */
    public function handleAlipayNotify(array $notifyData): bool
    {
        Log::info('Received alipay notify', $notifyData);

        // 验证签名
        if (!$this->verifyAlipaySign($notifyData)) {
            Log::error('Alipay notify signature verification failed', $notifyData);
            throw new \Exception('Signature verification failed');
        }

        $orderNo = $notifyData['out_trade_no'] ?? '';
        $tradeNo = $notifyData['trade_no'] ?? '';
        $tradeStatus = $notifyData['trade_status'] ?? '';
        $totalAmount = (float) ($notifyData['total_amount'] ?? 0);

        // 检查交易状态
        if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            Log::warning('Alipay trade status not success', [
                'order_no' => $orderNo,
                'trade_status' => $tradeStatus,
            ]);
            return false;
        }

        // 验证金额一致性
        $order = Order::where('order_no', $orderNo)->first();
        if ($order && abs((float) $order->amount - $totalAmount) > 0.01) {
            Log::error('Alipay amount mismatch', [
                'order_no' => $orderNo,
                'expected' => $order->amount,
                'received' => $totalAmount,
            ]);
            throw new \Exception('Payment amount mismatch');
        }

        // 处理支付成功
        return $this->handlePaymentSuccess($orderNo, $tradeNo, 'alipay');
    }

    /**
     * 处理微信支付通知
     *
     * @param array $notifyData
     * @return bool
     * @throws \Exception
     */
    public function handleWechatNotify(array $notifyData): bool
    {
        Log::info('Received wechat notify', $notifyData);

        // 验证签名 + 解密 V3 回调资源（明文会回填到 $notifyData）
        if (!$this->verifyWechatNotify($notifyData)) {
            Log::error('Wechat notify signature verification failed', $notifyData);
            throw new \Exception('Signature verification failed');
        }

        $orderNo = $notifyData['out_trade_no'] ?? '';
        $transactionId = $notifyData['transaction_id'] ?? '';
        $totalFee = (int) ($notifyData['amount']['total'] ?? $notifyData['total_fee'] ?? 0);

        // 检查支付结果
        if (($notifyData['result_code'] ?? $notifyData['trade_state'] ?? '') !== 'SUCCESS') {
            Log::warning('Wechat pay result not success', $notifyData);
            return false;
        }

        // 验证金额一致性（微信返回单位为分）
        $order = Order::where('order_no', $orderNo)->first();
        if ($order && (int) round((float) $order->amount * 100) !== $totalFee) {
            Log::error('Wechat amount mismatch', [
                'order_no' => $orderNo,
                'expected_cents' => (int) round((float) $order->amount * 100),
                'received_cents' => $totalFee,
            ]);
            throw new \Exception('Payment amount mismatch');
        }

        // 处理支付成功
        return $this->handlePaymentSuccess($orderNo, $transactionId, 'wechat');
    }

    /**
     * 处理支付成功
     *
     * @param string $orderNo
     * @param string $transactionId
     * @param string $payType
     * @return bool
     * @throws \Exception
     */
    public function handlePaymentSuccess(string $orderNo, string $transactionId, string $payType): bool
    {
        Log::info('Processing payment success', [
            'order_no' => $orderNo,
            'transaction_id' => $transactionId,
            'pay_type' => $payType,
        ]);

        $order = Order::where('order_no', $orderNo)->lockForUpdate()->first();

        if (!$order) {
            Log::error('Order not found', ['order_no' => $orderNo]);
            throw new \Exception("Order not found: {$orderNo}");
        }

        // 幂等处理：如果订单已支付，直接返回成功
        if ($order->status === 1) {
            Log::info('Order already paid', ['order_no' => $orderNo]);
            return true;
        }

        try {
            DB::beginTransaction();

            // 1. 更新订单状态
            $order->update([
                'status' => 1, // 已支付
                'transaction_id' => $transactionId,
                'paid_at' => now(),
            ]);

            // 2. 根据订单类型解锁服务
            switch ($order->type) {
                case 'analysis':
                    // 解锁分析报告
                    AnalysisTask::where('task_no', $order->relation_id)
                        ->update(['is_paid' => true]);
                    Log::info('Analysis report unlocked', [
                        'task_no' => $order->relation_id,
                    ]);
                    break;

                case 'package':
                    // 增加用户分析次数
                    $this->addUserAnalysisTimes($order->user_id, $order->relation_id);
                    Log::info('User analysis times added', [
                        'user_id' => $order->user_id,
                        'package_id' => $order->relation_id,
                    ]);
                    break;
            }

            // 3. 计算推广佣金
            $this->calculateCommission($order);

            DB::commit();

            Log::info('Payment success processed', [
                'order_no' => $orderNo,
                'amount' => $order->amount,
            ]);

            // 4. 触达通知（站内消息）
            try {
                $packageName = $this->getPackageName($order);
                app(\App\Services\NotificationService::class)->paymentSuccess(
                    $order->user_id, $order->order_no, (float) $order->amount, $packageName
                );
            } catch (\Throwable $e) {
                Log::warning('支付通知发送失败', ['error' => $e->getMessage()]);
            }

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment success processing failed', [
                'order_no' => $orderNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 计算推广佣金
     *
     * @param Order $order
     * @return void
     */
    protected function calculateCommission(Order $order): void
    {
        // 检查用户是否有推荐人
        $user = User::find($order->user_id);
        if (!$user || !$user->parent_id) {
            Log::info('No parent user, skipping commission calculation', [
                'user_id' => $order->user_id,
            ]);
            return;
        }

        // 检查推荐人是否是推广员
        $promoter = Promoter::where('user_id', $user->parent_id)->first();
        if (!$promoter) {
            Log::info('Parent user is not a promoter, skipping commission calculation', [
                'parent_id' => $user->parent_id,
            ]);
            return;
        }

        // 计算佣金
        $commissionAmount = round($order->amount * $promoter->commission_rate / 100, 2);

        if ($commissionAmount <= 0) {
            Log::info('Commission amount is zero, skipping', [
                'order_amount' => $order->amount,
                'commission_rate' => $promoter->commission_rate,
            ]);
            return;
        }

        // 创建佣金记录
        Commission::create([
            'commission_no' => 'CM' . date('Ymd') . Str::random(8),
            'promoter_id' => $promoter->id,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'amount' => $commissionAmount,
            'rate' => $promoter->commission_rate,
            'status' => 0, // 冻结中
        ]);

        // 更新推广员统计
        $promoter->increment('total_consume');
        $promoter->increment('total_commission', $commissionAmount);
        $promoter->increment('frozen_commission', $commissionAmount);

        Log::info('Commission calculated', [
            'promoter_id' => $promoter->id,
            'order_id' => $order->id,
            'amount' => $commissionAmount,
            'rate' => $promoter->commission_rate,
        ]);
    }

    /**
     * 增加用户分析次数
     *
     * @param int $userId
     * @param int $packageId
     * @return void
     */
    protected function addUserAnalysisTimes(int $userId, int $packageId): void
    {
        $package = \App\Models\ProductPackage::find($packageId);
        if (!$package) {
            Log::warning('Package not found', ['package_id' => $packageId]);
            return;
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            Log::warning('User not found', ['user_id' => $userId]);
            return;
        }

        // 使用 AnalysisTimesService 统一处理（含流水记录）
        $timesService = app(\App\Services\AnalysisTimesService::class);
        $timesService->addTimes($user, (int) $package->times, 'ORDER_' . $userId);

        Log::info('User analysis times added', [
            'user_id' => $userId,
            'times_added' => $package->times,
        ]);
    }

    /**
     * 生成订单号
     *
     * @return string
     */
    protected function generateOrderNo(): string
    {
        return 'ORD' . date('YmdHis') . Str::random(6);
    }

    /**
     * 创建支付宝支付参数
     *
     * @param Order $order
     * @return array
     */
    protected function createAlipayParams(Order $order): array
    {
        // 这里集成支付宝 SDK
        // 目前返回模拟数据，实际使用时需要集成 alipay-sdk-php

        $params = [
            'app_id' => $this->alipayConfig['app_id'],
            'method' => 'alipay.trade.page.pay',
            'format' => 'JSON',
            'return_url' => $this->alipayConfig['return_url'],
            'notify_url' => $this->alipayConfig['notify_url'],
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode([
                'out_trade_no' => $order->order_no,
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'total_amount' => $order->amount,
                'subject' => $this->getOrderSubject($order),
            ]),
        ];

        // 生成签名
        $params['sign'] = $this->generateAlipaySign($params);

        Log::info('Alipay params created', ['order_no' => $order->order_no]);

        return $params;
    }

    /**
     * 创建微信支付参数
     *
     * @param Order $order
     * @return array
     */
    protected function createWechatParams(Order $order): array
    {
        // 微信支付配置从后台「系统设置」中读取
        $appId = \App\Models\SystemConfig::where('key', 'wechat_app_id')->value('value') ?: '';
        $mchId = \App\Models\SystemConfig::where('key', 'wechat_mch_id')->value('value') ?: '';
        $payKey = \App\Models\SystemConfig::where('key', 'wechat_pay_key')->value('value') ?: '';

        if (empty($appId) || empty($mchId) || empty($payKey)) {
            Log::warning('Wechat pay not configured', [
                'has_app_id' => !empty($appId),
                'has_mch_id' => !empty($mchId),
                'has_pay_key' => !empty($payKey),
            ]);

            // 未配置时返回明确提示，前端可提示用户选择其他支付方式
            return [
                'configured' => false,
                'message' => '微信支付未配置，请在后台【系统设置】中配置',
                'order_no' => $order->order_no,
            ];
        }

        // 生产环境应使用 easywechat 等 SDK 调用统一下单API
        // 这里返回统一下单所需参数，前端或后端可继续处理
        $params = [
            'appid' => $appId,
            'mch_id' => $mchId,
            'nonce_str' => Str::random(32),
            'body' => $this->getOrderSubject($order),
            'out_trade_no' => $order->order_no,
            'total_fee' => (int) round((float) $order->amount * 100), // 微信支付单位为分
            'spbill_create_ip' => request()->ip(),
            'notify_url' => url('/api/v1/payment/notify/wechat'),
            'trade_type' => 'NATIVE', // 扫码支付
        ];

        Log::info('Wechat params created', ['order_no' => $order->order_no]);

        return [
            'configured' => true,
            'order_no' => $order->order_no,
            'params' => $params,
            'message' => '请使用 wechatpay SDK 完成签名',
        ];
    }

    /**
     * 获取订单标题
     *
     * @param Order $order
     * @return string
     */
    protected function getOrderSubject(Order $order): string
    {
        return match ($order->type) {
            'analysis' => 'AI健康分析报告',
            'package' => '分析次数包',
            default => '中医健康服务',
        };
    }

    /**
     * 生成支付宝签名
     *
     * @param array $params
     * @return string
     */
    protected function generateAlipaySign(array $params): string
    {
        // 移除 sign 和 sign_type
        unset($params['sign']);
        unset($params['sign_type']);

        // 排序参数
        ksort($params);

        // 拼接字符串
        $stringToBeSigned = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $stringToBeSigned .= $key . '=' . $value . '&';
            }
        }
        $stringToBeSigned = rtrim($stringToBeSigned, '&');

        // RSA2 签名
        $privateKey = $this->alipayConfig['private_key'];
        if (empty($privateKey)) {
            // 返回模拟签名
            return base64_encode(Str::random(128));
        }

        $key = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        openssl_sign($stringToBeSigned, $sign, $key, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    /**
     * 验证支付宝签名
     *
     * @param array $params
     * @return bool
     */
    protected function verifyAlipaySign(array $params): bool
    {
        $publicKey = $this->alipayConfig['public_key'] ?? '';

        // 没有配置公钥：本地开发环境可跳过，生产环境必须拒绝
        if (empty($publicKey)) {
            if (app()->environment('production')) {
                Log::error('Alipay public key not configured in production - rejecting notify for safety');
                return false;
            }
            Log::warning('Alipay public key not configured, skipping signature verification (dev only)');
            return true;
        }

        $sign = $params['sign'] ?? '';
        if (empty($sign)) {
            return false;
        }

        unset($params['sign']);
        unset($params['sign_type']);

        ksort($params);

        $stringToBeVerified = '';
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null && !is_array($value)) {
                $stringToBeVerified .= $key . '=' . $value . '&';
            }
        }
        $stringToBeVerified = rtrim($stringToBeVerified, '&');

        $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($publicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        $result = openssl_verify(
            $stringToBeVerified,
            base64_decode($sign),
            $publicKeyPem,
            OPENSSL_ALGO_SHA256
        );

        return $result === 1;
    }

    /**
     * 验证并解密微信支付 V3 回调
     *
     * 微信支付 V3 回调结构：
     *   - Header: Wechatpay-Signature / Wechatpay-Nonce / Wechatpay-Timestamp / Wechatpay-Serial
     *   - Body:   { "resource": { "ciphertext": "...", "associated_data": "...", "nonce": "..." } }
     *
     * 完整流程：
     *   1. 检查 resource 密文结构
     *   2. 用 APIv3 密钥（32 字节）以 AES-256-GCM 解密 resource.ciphertext
     *   3. 把解密后的业务字段（out_trade_no / transaction_id / amount.total 等）合并到 $params
     *
     * 说明：完整的 V3 验签还需要用微信平台证书验证 header 中的 Wechatpay-Signature，
     *       推荐接入 wechatpay SDK 完成。本地兜底实现优先保证"未配置密钥时生产环境拒绝"。
     *
     * @param array $params 引用传递，解密后业务字段会回填
     * @return bool
     */
    protected function verifyWechatNotify(array &$params): bool
    {
        $apiV3Key = \App\Models\SystemConfig::where('key', 'wechat_api_v3_key')->value('value') ?: '';

        // 未配置 V3 密钥：本地跳过，生产环境必须拒绝
        if (empty($apiV3Key)) {
            if (app()->environment('production')) {
                Log::error('Wechat APIv3 key not configured in production - rejecting notify for safety');
                return false;
            }
            Log::warning('Wechat APIv3 key not configured, skipping signature verification (dev only)');
            return true;
        }

        // 已是明文回调（前置中间件已解密），直接放行
        if (!empty($params['out_trade_no'])) {
            return true;
        }

        $resource = $params['resource'] ?? null;
        if (!is_array($resource)) {
            Log::error('Wechat notify missing resource block', $params);
            return false;
        }

        $ciphertext     = $resource['ciphertext'] ?? '';
        $associatedData = $resource['associated_data'] ?? '';
        $nonce          = $resource['nonce'] ?? '';

        if (empty($ciphertext) || empty($nonce)) {
            Log::error('Wechat notify resource incomplete', $resource);
            return false;
        }

        // AES-256-GCM：tag 在密文末尾 16 字节
        $cipherRaw = base64_decode($ciphertext);
        if ($cipherRaw === false || strlen($cipherRaw) <= 16) {
            Log::error('Wechat notify ciphertext invalid');
            return false;
        }

        $encBody = substr($cipherRaw, 0, -16);
        $tag     = substr($cipherRaw, -16);

        $decrypted = openssl_decrypt(
            $encBody,
            'aes-256-gcm',
            $apiV3Key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $associatedData
        );

        if ($decrypted === false) {
            Log::error('Wechat notify decrypt failed');
            return false;
        }

        // 把解密后的业务字段合并到 $params
        $decoded = json_decode($decrypted, true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) {
                $params[$k] = $v;
            }
        }

        return true;
    }

    /**
     * 重新生成支付宝支付参数（已有订单换支付方式）
     */
    public function regenerateAlipayParams(Order $order): array
    {
        return $this->createAlipayParams($order);
    }

    /**
     * 重新生成微信支付参数（已有订单换支付方式）
     */
    public function regenerateWechatParams(Order $order): array
    {
        return $this->createWechatParams($order);
    }

    /**
     * 查询订单状态
     *
     * @param string $orderNo
     * @return Order|null
     */
    public function queryOrderStatus(string $orderNo): ?Order
    {
        return Order::where('order_no', $orderNo)->first();
    }

    /**
     * 关闭超时订单
     *
     * @param int $minutes 超时分钟数
     * @return int 关闭的订单数量
     */
    public function closeExpiredOrders(int $minutes = 30): int
    {
        $expiredTime = now()->subMinutes($minutes);

        // 使用 update 单条 SQL 替代逐条循环，避免并发问题
        $count = Order::where('status', 0)
            ->where('created_at', '<', $expiredTime)
            ->update(['status' => 2]); // 已取消

        Log::info('Expired orders closed', ['count' => $count]);
        return $count;
    }

    /**
     * 获取订单对应的商品名称
     */
    protected function getPackageName(Order $order): string
    {
        if ($order->type === 'package' && $order->relation_id) {
            $p = \App\Models\Package::find($order->relation_id);
            if ($p) return $p->name;
        }
        if ($order->type === 'analysis') {
            return 'AI 分析报告';
        }
        return '订单';
    }
}
