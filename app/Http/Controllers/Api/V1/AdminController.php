<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisTask;
use App\Models\Order;
use App\Models\User;
use App\Models\Promoter;
use App\Models\Withdraw;
use App\Models\ConstitutionQuestion;
use App\Models\Article;
use App\Models\ProductPackage;
use App\Models\AiModel;
use App\Models\AiLog;
use App\Models\SystemConfig;
use App\Models\Admin;
use App\Services\LlmService;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ===== 管理员登录 =====
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $admin = Admin::where('username', $request->username)->first();
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['code' => 401, 'message' => '账号或密码错误'], 401);
        }

        $token = $admin->createToken('admin_token')->plainTextToken;
        return response()->json(['code' => 0, 'message' => '登录成功', 'data' => compact('token', 'admin')]);
    }

    // ===== 数据概览 =====
    public function dashboard()
    {
        $today = now()->startOfDay();

        // 今日数据
        $todayRegister = User::where('created_at', '>=', $today)->count();
        $todayPaid = Order::where('created_at', '>=', $today)->where('status', 1)->count();
        $todayIncome = Order::where('created_at', '>=', $today)->where('status', 1)->sum('amount') ?? 0;

        // ✅ 今日产生的佣金（从 commissions 表统计）而非"今日提现金额"
        $todayCommission = \App\Models\Commission::where('created_at', '>=', $today)->sum('amount') ?? 0;

        $todayAiCalls = AiLog::where('created_at', '>=', $today)->count();
        $todayAiCost = AiLog::where('created_at', '>=', $today)->sum('cost') ?? 0;

        // 累计数据
        $totalUsers = User::count();
        $totalOrders = Order::count();
        // 累计已结算佣金
        $totalCommission = \App\Models\Commission::where('status', 1)->sum('amount') ?? 0;
        // 累计提现金额
        $totalWithdraw = Withdraw::where('status', 1)->sum('amount') ?? 0;
        // 利润 = 收入 - 已提现佣金 - AI 成本
        $totalIncome = Order::where('status', 1)->sum('amount') ?? 0;
        $totalAiCost = AiLog::sum('cost') ?? 0;
        $totalProfit = $totalIncome - $totalWithdraw - $totalAiCost;

        // ✅ today_visits: 通过 Redis 或缓存键自增的访问量
        // 这里从 cache 读取今日访问数（中间件负责自增）
        $todayVisits = \Illuminate\Support\Facades\Cache::get('stats:visits:' . date('Ymd'), 0);

        return response()->json(['code' => 0, 'data' => [
            'today_visits' => $todayVisits,
            'today_register' => $todayRegister,
            'today_paid' => $todayPaid,
            'today_income' => round($todayIncome, 2),
            'today_commission' => round($todayCommission, 2),
            'today_ai_calls' => $todayAiCalls,
            'today_ai_cost' => round($todayAiCost, 2),
            'today_profit' => round($todayIncome - $todayCommission - $todayAiCost, 2),
            'total_users' => $totalUsers,
            'total_orders' => $totalOrders,
            'total_commission' => round($totalCommission, 2),
            'total_withdraw' => round($totalWithdraw, 2),
            'total_profit' => round($totalProfit, 2),
        ]]);
    }

    /**
     * 滚动播报：最近邀请注册 + 佣金返利数据
     * 用于后台用户管理页面顶部滚动展示
     *
     * 优化：避免 N+1 查询，用 withCount + 预加载佣金汇总
     */
    public function inviteMarquee()
    {
        // 最近 50 条邀请注册记录（预加载关联）
        $registrations = \App\Models\InviteRegistration::with(['user', 'promoter.user'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // 预加载每个推广员的邀请人数（1 次查询替代 N 次 count）
        $promoterIds = $registrations->pluck('promoter_id')->unique()->filter();
        $inviteCounts = $promoterIds->isEmpty()
            ? []
            : \App\Models\InviteRegistration::whereIn('promoter_id', $promoterIds)
                ->selectRaw('promoter_id, COUNT(*) as cnt')
                ->groupBy('promoter_id')
                ->pluck('cnt', 'promoter_id')
                ->toArray();

        // 预加载每个推广员的佣金汇总（1 次查询替代 N 次 sum）
        $commissionSums = $promoterIds->isEmpty()
            ? []
            : \App\Models\Commission::whereIn('promoter_id', $promoterIds)
                ->selectRaw('promoter_id, COALESCE(SUM(amount), 0) as total')
                ->groupBy('promoter_id')
                ->pluck('total', 'promoter_id')
                ->toArray();

        $items = [];
        foreach ($registrations as $reg) {
            $promoterName = $reg->promoter?->user?->nickname
                ?? $reg->promoter?->user?->name
                ?? '推广员';
            $inviteeName = $reg->user?->nickname
                ?? $reg->user?->name
                ?? '用户';

            $pid = $reg->promoter_id;
            $items[] = [
                'id' => $reg->id,
                'promoter_name' => $promoterName,
                'invitee_name' => $inviteeName,
                'invite_count' => (int) ($inviteCounts[$pid] ?? 0),
                'commission' => round((float) ($commissionSums[$pid] ?? 0), 2),
                'is_fraud' => $reg->is_fraud,
                'created_at' => $reg->created_at->format('Y-m-d H:i'),
            ];
        }

        // 补充推广员汇总（邀请人数 + 总佣金）
        $topPromoters = \App\Models\Promoter::with('user')
            ->orderByDesc('total_invite')
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'promoter_name' => $p->user?->nickname ?? $p->user?->name ?? '推广员',
                'invite_count' => $p->total_invite,
                'commission' => round($p->total_commission, 2),
            ]);

        return response()->json([
            'code' => 0,
            'data' => [
                'recent' => $items,
                'top_list' => $topPromoters,
            ],
        ]);
    }

    // ===== 用户管理 =====
    public function users(Request $request)
    {
        $query = User::query();
        if ($request->phone) $query->where('mobile', 'like', "%{$request->phone}%");
        if ($request->nickname) $query->where('nickname', 'like', "%{$request->nickname}%");
        return response()->json(['code' => 0, 'data' => $query->paginate($request->per_page ?? 10)]);
    }

    public function userDetail($id)
    {
        $user = User::with('profile', 'orders')->findOrFail($id);
        return response()->json(['code' => 0, 'data' => $user]);
    }

    /**
     * 编辑用户信息
     */
    public function userUpdate(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'nickname' => 'sometimes|string|max:50',
            'name' => 'sometimes|nullable|string|max:50',
            'mobile' => 'sometimes|nullable|string|max:20|unique:users,mobile,' . $id,
            'email' => 'sometimes|nullable|email|max:100|unique:users,email,' . $id,
            'avatar' => 'sometimes|nullable|string|max:255',
            'gender' => 'sometimes|nullable|in:1,2,0',
            'birthday' => 'sometimes|nullable|date',
            'is_promoter' => 'sometimes|boolean',
            'status' => 'sometimes|integer|in:0,1',
        ]);

        $user->fill($data)->save();

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * 切换用户状态（启用/禁用）
     */
    public function userToggleStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:0,1',
        ]);

        $user = User::findOrFail($id);
        $user->status = (int) $data['status'];
        $user->save();

        return response()->json([
            'code' => 0,
            'message' => $user->status === 1 ? '已启用' : '已禁用',
            'data' => $user,
        ]);
    }

    /**
     * 重置用户密码（管理员操作，无需验证原密码）
     */
    public function userResetPassword(Request $request, $id)
    {
        $data = $request->validate([
            'password' => 'required|string|min:6|max:32',
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($data['password']);
        $user->save();

        // 撤销该用户所有 token，强制重新登录
        $user->tokens()->delete();

        return response()->json([
            'code' => 0,
            'message' => '密码已重置，用户需重新登录',
            'data' => $user,
        ]);
    }

    // ===== 用户余额管理 =====

    /**
     * 调整用户余额（充值或扣减）
     * POST /admin/users/{id}/balance
     * body: { type: 'recharge'|'admin_deduct', amount: 9.99, remark: '...' }
     */
    public function userAdjustBalance(Request $request, $id)
    {
        $data = $request->validate([
            'type'   => 'required|in:recharge,admin_deduct',
            'amount' => 'required|numeric|min:0.01|max:99999.99',
            'remark' => 'nullable|string|max:200',
        ]);

        $user = User::findOrFail($id);
        $adminId = optional($request->user())->id;

        $amount = (float) $data['amount'];
        $change = $data['type'] === 'recharge' ? $amount : -$amount;

        // 扣减时校验余额是否够
        if ($change < 0 && (float) $user->balance < $amount) {
            return response()->json([
                'code' => 422,
                'message' => '用户余额不足，当前余额 ¥' . number_format((float) $user->balance, 2),
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $change, $data, $adminId) {
            $before = (float) $user->balance;
            $after  = round($before + $change, 2);

            $user->balance = $after;
            $user->save();

            \App\Models\UserBalanceLog::create([
                'user_id'     => $user->id,
                'change'      => $change,
                'before'      => $before,
                'after'       => $after,
                'type'        => $data['type'],
                'remark'      => $data['remark'] ?? ($data['type'] === 'recharge' ? '管理员后台充值' : '管理员后台扣减'),
                'operator_id' => $adminId,
            ]);
        });

        return response()->json([
            'code'    => 0,
            'message' => $data['type'] === 'recharge' ? '充值成功' : '扣减成功',
            'data'    => [
                'balance' => (float) $user->fresh()->balance,
            ],
        ]);
    }

    /**
     * 用户余额变动流水
     * GET /admin/users/{id}/balance-logs?type=&page=&per_page=
     */
    public function userBalanceLogs(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $query = \App\Models\UserBalanceLog::where('user_id', $user->id)
            ->with('operator:id,username,name')
            ->orderBy('created_at', 'desc');
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $paginator = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'code' => 0,
            'data' => [
                'balance' => (float) $user->balance,
                'logs'    => $paginator,
            ],
        ]);
    }

    // ===== 订单管理 =====
    public function orders(Request $request)
    {
        $query = Order::query();
        if ($request->order_no) $query->where('order_no', $request->order_no);
        if ($request->status !== null) $query->where('status', $request->status);
        return response()->json(['code' => 0, 'data' => $query->paginate($request->per_page ?? 10)]);
    }

    public function orderDetail($orderNo)
    {
        $order = Order::where('order_no', $orderNo)->firstOrFail();
        return response()->json(['code' => 0, 'data' => $order]);
    }

    // ===== AI管理 =====
    public function aiModels()
    {
        return response()->json(['code' => 0, 'data' => AiModel::orderBy('sort_order')->get()]);
    }

    public function aiModelStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string|in:openai,anthropic,deepseek,doubao',
            'model' => 'required|string|max:100',
            'api_key' => 'required|string|max:500',
            'api_url' => 'nullable|url|max:500',
            'tokens_price' => 'nullable|numeric|min:0',
            'timeout' => 'nullable|integer|min:1|max:120',
            'retry_times' => 'nullable|integer|min:0|max:10',
            'is_enabled' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $model = AiModel::create($data);
        return response()->json(['code' => 0, 'data' => $model]);
    }

    public function aiModelUpdate(Request $request, $id)
    {
        $model = AiModel::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'provider' => 'sometimes|string|in:openai,anthropic,deepseek,doubao',
            'model' => 'sometimes|string|max:100',
            'api_key' => 'sometimes|string|max:500',
            'api_url' => 'nullable|url|max:500',
            'tokens_price' => 'nullable|numeric|min:0',
            'timeout' => 'nullable|integer|min:1|max:120',
            'retry_times' => 'nullable|integer|min:0|max:10',
            'is_enabled' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $model->update($data);
        return response()->json(['code' => 0, 'data' => $model]);
    }

    public function aiLogs(Request $request)
    {
        return response()->json(['code' => 0, 'data' => AiLog::with('user')->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10)]);
    }

    // ===== 推广管理 =====
    public function promoters(Request $request)
    {
        $query = Promoter::with('user');
        if ($request->nickname) $query->whereHas('user', fn($q) => $q->where('nickname', 'like', "%{$request->nickname}%"));
        if ($request->filled('mobile')) {
            $query->whereHas('user', fn($q) => $q->where('mobile', 'like', '%' . $request->mobile . '%'));
        }
        $paginator = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);
        return response()->json(['code' => 0, 'data' => $paginator]);
    }

    public function promoterDetail($id)
    {
        $promoter = Promoter::with('user')->findOrFail($id);

        // 关联统计
        $stats = [
            'total_commission' => (float) $promoter->total_commission,
            'frozen_commission' => (float) $promoter->frozen_commission,
            'withdrawn_commission' => (float) $promoter->withdrawn_commission,
            'available_commission' => (float) ($promoter->total_commission - $promoter->frozen_commission - $promoter->withdrawn_commission),
            'direct_users' => User::where('parent_id', $promoter->user_id)->count(),
            'paid_orders' => \App\Models\Order::where('user_id', $promoter->user_id)->where('status', 1)->count(),
        ];

        return response()->json([
            'code' => 0,
            'data' => [
                'promoter' => $promoter,
                'stats' => $stats,
                'recent_commissions' => \App\Models\Commission::where('promoter_id', $promoter->id)
                    ->orderBy('created_at', 'desc')->limit(10)->get(),
            ],
        ]);
    }

    public function promoterToggle($id)
    {
        $promoter = Promoter::findOrFail($id);
        $promoter->is_enabled = !($promoter->is_enabled ?? 1);
        $promoter->save();
        return response()->json([
            'code' => 0,
            'message' => $promoter->is_enabled ? '已启用推广员' : '已禁用推广员',
            'data' => $promoter,
        ]);
    }

    // ===== 提现审核 =====
    public function withdraws(Request $request)
    {
        $query = Withdraw::query();
        if ($request->status !== null) $query->where('status', $request->status);
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10)]);
    }

    public function withdrawAudit(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:1,2',
            'remark' => 'nullable|string|max:255',
            // 1=已支付 0=未支付（默认0，审核通过时不一定立即打款）
            'is_paid' => 'nullable|boolean',
        ]);

        $withdraw = Withdraw::findOrFail($id);

        // 只能审核待审核的提现
        if ($withdraw->status !== 0) {
            return response()->json(['code' => 400, 'message' => '该提现已审核'], 400);
        }

        $isApprove = $request->status == 1;
        $isPaid = $request->boolean('is_paid'); // 是否已实际打款

        // 使用事务确保数据一致性
        \Illuminate\Support\Facades\DB::transaction(function () use ($withdraw, $isApprove, $isPaid, $request) {
            if ($isApprove) {
                // 审核通过：先更新 withdraw 状态
                $updateData = [
                    'status' => 1,
                    'audit_remark' => $request->remark,
                    'audited_at' => now(),
                ];

                // 只有真正打款后才设置 paid_at
                if ($isPaid) {
                    $updateData['paid_at'] = now();
                }

                $withdraw->update($updateData);

                // 找到这次提现关联的佣金记录，按比例从冻结变为已提现
                // 由于提现时没有和 commissions 关联，这里通过 user_id + 时间段反查最近的冻结佣金
                $promoter = $withdraw->promoter;
                if ($promoter) {
                    // 扣除冻结佣金
                    $promoter->decrement('frozen_commission', $withdraw->amount);

                    // 实际打款后才计入已提现
                    if ($isPaid) {
                        $promoter->increment('withdrawn_commission', $withdraw->amount);
                    }

                    // 找到最近的冻结中佣金记录，标记为已结算
                    // 取累计冻结金额中等于本次提现金额的最新一条
                    $remainingAmount = $withdraw->amount;
                    $commissions = \App\Models\Commission::where('promoter_id', $promoter->id)
                        ->where('status', 0) // 冻结中
                        ->orderBy('created_at', 'asc')
                        ->get();

                    foreach ($commissions as $commission) {
                        if ($remainingAmount <= 0) break;
                        if ($commission->amount <= $remainingAmount) {
                            $commission->update(['status' => 1]); // 已结算
                            $remainingAmount -= $commission->amount;
                        } else {
                            // 拆单：超出部分需要新记录（这里简化处理，按比例标记为部分结算）
                            $commission->update(['status' => 1]);
                            $remainingAmount = 0;
                        }
                    }
                }
            } else {
                // 审核拒绝，解冻佣金
                $withdraw->update([
                    'status' => 2,
                    'audit_remark' => $request->remark,
                    'audited_at' => now(),
                ]);

                $promoter = $withdraw->promoter;
                if ($promoter) {
                    $promoter->decrement('frozen_commission', $withdraw->amount);
                }
            }
        });

        return response()->json(['code' => 0, 'message' => '审核完成']);
    }

    // ===== 文章管理 =====
    public function articles(Request $request)
    {
        $query = Article::query();
        if ($request->title) $query->where('title', 'like', "%{$request->title}%");
        return response()->json(['code' => 0, 'data' => $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 10)]);
    }

    public function articleStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'cover' => 'nullable|url|max:500',
            'is_published' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $article = Article::create($data);
        return response()->json(['code' => 0, 'data' => $article]);
    }

    public function articleUpdate(Request $request, $id)
    {
        $article = Article::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
            'category' => 'nullable|string|max:50',
            'cover' => 'nullable|url|max:500',
            'is_published' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $article->update($data);
        return response()->json(['code' => 0, 'data' => $article]);
    }

    public function articleDelete($id)
    {
        Article::findOrFail($id)->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    // ===== 系统配置 =====
    public function configs()
    {
        $configs = SystemConfig::all()->groupBy('group_name');
        return response()->json(['code' => 0, 'data' => $configs]);
    }

    public function configUpdate(Request $request)
    {
        // 白名单：只允许修改这些配置
        $allowedKeys = [
            'site_name', 'site_logo', 'site_description',
            'wechat_appid', 'wechat_secret', 'wechat_mch_id', 'wechat_pay_key',
            'alipay_app_id', 'alipay_private_key', 'alipay_public_key',
            'ai_model_id', 'analysis_mode',
            'sms_bao_user', 'sms_bao_pass',
            'cos_region', 'cos_bucket', 'cos_secret_id', 'cos_secret_key',
            'commission_level1', 'commission_level2',
            'withdraw_min_amount',
            // LLM配置
            'llm_provider', 'llm_api_url', 'llm_api_key', 'llm_model',
            'llm_temperature', 'llm_max_tokens', 'llm_timeout',
        ];

        $data = $request->only($allowedKeys);
        foreach ($data as $key => $value) {
            // 使用 SystemConfigService 以支持加密存储
            SystemConfigService::set($key, $value);
        }
        return response()->json(['code' => 0, 'message' => '保存成功']);
    }

    // ===== 次数包管理 =====
    public function packages(Request $request)
    {
        $query = ProductPackage::query();
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('is_enabled')) {
            $query->where('is_enabled', $request->is_enabled);
        }
        $paginator = $query->orderBy('sort_order')
            ->paginate($request->per_page ?? 10);
        return response()->json(['code' => 0, 'data' => $paginator]);
    }

    public function packageStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'required|in:tongue,face,all',
            'times' => 'required|integer|min:1',
            'days' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'is_recommend' => 'nullable|boolean',
            'is_enabled' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);
        $data['original_price'] = $data['original_price'] ?? 0;
        $data['is_recommend'] = $data['is_recommend'] ?? 0;
        $data['is_enabled'] = $data['is_enabled'] ?? 1;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $package = ProductPackage::create($data);
        return response()->json(['code' => 0, 'message' => '创建成功', 'data' => $package]);
    }

    public function packageUpdate(Request $request, $id)
    {
        $package = ProductPackage::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'type' => 'sometimes|in:tongue,face,all',
            'times' => 'sometimes|integer|min:1',
            'days' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'original_price' => 'sometimes|numeric|min:0',
            'is_recommend' => 'sometimes|boolean',
            'is_enabled' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);
        $package->update($data);
        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $package]);
    }

    public function packageDestroy($id)
    {
        $package = ProductPackage::findOrFail($id);
        // 检查是否有未支付订单
        $hasUnpaid = \App\Models\Order::where('type', 'package')
            ->where('relation_id', $package->id)
            ->where('status', 0)
            ->exists();
        if ($hasUnpaid) {
            return response()->json(['code' => 400, 'message' => '该套餐存在未支付订单，无法删除'], 400);
        }
        $package->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    public function packageToggle($id)
    {
        $package = ProductPackage::findOrFail($id);
        $package->is_enabled = !$package->is_enabled;
        $package->save();
        return response()->json([
            'code' => 0,
            'message' => $package->is_enabled ? '已启用' : '已禁用',
            'data' => $package,
        ]);
    }

    // ===== 体质题目管理 =====
    public function constitutionQuestions(Request $request)
    {
        $query = ConstitutionQuestion::query();
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('is_enabled')) {
            $query->where('is_enabled', $request->is_enabled);
        }
        return response()->json([
            'code' => 0,
            'data' => $query->orderBy('category')->orderBy('sort_order')->get()
        ]);
    }

    public function constitutionQuestionStore(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|max:50',
            'question' => 'required|string|max:200',
            'type' => 'required|in:single,multi',
            'options' => 'required|array|min:2',
            'sort_order' => 'nullable|integer',
            'is_enabled' => 'nullable|boolean',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_enabled'] = $data['is_enabled'] ?? 1;
        $q = ConstitutionQuestion::create($data);
        return response()->json(['code' => 0, 'message' => '创建成功', 'data' => $q]);
    }

    public function constitutionQuestionUpdate(Request $request, $id)
    {
        $q = ConstitutionQuestion::findOrFail($id);
        $data = $request->validate([
            'category' => 'sometimes|string|max:50',
            'question' => 'sometimes|string|max:200',
            'type' => 'sometimes|in:single,multi',
            'options' => 'sometimes|array|min:2',
            'sort_order' => 'sometimes|integer',
            'is_enabled' => 'sometimes|boolean',
        ]);
        $q->update($data);
        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $q]);
    }

    public function constitutionQuestionDestroy($id)
    {
        ConstitutionQuestion::findOrFail($id)->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    // ===== 大模型测试 =====
    public function testLlmConnection()
    {
        $llmService = new LlmService();
        $result = $llmService->testConnection();

        if ($result['success']) {
            return response()->json([
                'code' => 0,
                'message' => '连接成功',
                'data' => [
                    'response' => $result['content'],
                ],
            ]);
        }

        return response()->json([
            'code' => 500,
            'message' => $result['error'],
        ], 500);
    }

    // ===== 管理员自身管理 =====
    /**
     * 获取当前登录管理员信息
     */
    public function adminInfo(Request $request)
    {
        $admin = $request->user();
        if (!$admin) {
            return response()->json(['code' => 401, 'message' => '未登录'], 401);
        }
        $admin->makeHidden(['password']);
        return response()->json(['code' => 0, 'data' => $admin]);
    }

    /**
     * 修改当前管理员自己的密码
     */
    public function changeOwnPassword(Request $request)
    {
        $data = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|max:32|different:old_password',
        ]);

        $admin = $request->user();
        if (!$admin) {
            return response()->json(['code' => 401, 'message' => '未登录'], 401);
        }

        if (!Hash::check($data['old_password'], $admin->password)) {
            return response()->json(['code' => 422, 'message' => '原密码错误'], 422);
        }

        $admin->password = Hash::make($data['new_password']);
        $admin->save();

        // 撤销当前所有 token，强制重新登录
        $admin->tokens()->delete();

        return response()->json([
            'code' => 0,
            'message' => '密码修改成功，请重新登录',
        ]);
    }

    // ===== 管理员管理（管理其他后台账号） =====
    /**
     * 管理员列表
     */
    public function adminList(Request $request)
    {
        $query = Admin::query();
        if ($request->filled('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        $paginator = $query->orderBy('id', 'desc')->paginate($request->per_page ?? 10);
        // 隐藏密码字段
        $paginator->getCollection()->transform(function ($admin) {
            $admin->makeHidden(['password']);
            return $admin;
        });
        return response()->json(['code' => 0, 'data' => $paginator]);
    }

    /**
     * 新增管理员
     */
    public function adminStore(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|min:3|max:50|unique:admins,username',
            'password' => 'required|string|min:6|max:32',
            'name' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'status' => 'nullable|in:0,1',
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['status'] = $data['status'] ?? 1;

        $admin = Admin::create($data);
        $admin->makeHidden(['password']);

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => $admin,
        ]);
    }

    /**
     * 更新管理员
     */
    public function adminUpdate(Request $request, $id)
    {
        $admin = Admin::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|nullable|string|max:50',
            'email' => 'sometimes|nullable|email|max:100',
            'status' => 'sometimes|in:0,1',
        ]);

        $admin->fill($data);
        $admin->save();
        $admin->makeHidden(['password']);

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $admin,
        ]);
    }

    /**
     * 重置指定管理员的密码（超级管理员可重置其他账号密码）
     */
    public function adminResetPassword(Request $request, $id)
    {
        $data = $request->validate([
            'password' => 'required|string|min:6|max:32',
        ]);

        $currentAdmin = $request->user();
        $admin = Admin::findOrFail($id);

        // 防止修改自己密码时误用此接口（自己用 changeOwnPassword）
        if ($currentAdmin && $currentAdmin->id == $admin->id) {
            return response()->json(['code' => 422, 'message' => '请使用"修改我的密码"功能'], 422);
        }

        $admin->password = Hash::make($data['password']);
        $admin->save();
        $admin->tokens()->delete();
        $admin->makeHidden(['password']);

        return response()->json([
            'code' => 0,
            'message' => '密码已重置，该账号需重新登录',
            'data' => $admin,
        ]);
    }

    /**
     * 删除管理员
     */
    public function adminDestroy(Request $request, $id)
    {
        $currentAdmin = $request->user();
        if ($currentAdmin && $currentAdmin->id == (int) $id) {
            return response()->json(['code' => 422, 'message' => '不能删除自己'], 422);
        }

        $admin = Admin::findOrFail($id);

        // 至少保留一个超级管理员（id=1）
        if ((int) $admin->id === 1) {
            return response()->json(['code' => 422, 'message' => '系统超级管理员不可删除'], 422);
        }

        $admin->tokens()->delete();
        $admin->delete();

        return response()->json([
            'code' => 0,
            'message' => '已删除',
        ]);
    }
}
