<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\RiskBlacklist;
use App\Models\RiskEvent;
use App\Models\RiskRule;
use App\Services\RiskControlService;
use Illuminate\Http\Request;

/**
 * 管理后台 - 风控管理
 */
class RiskController extends Controller
{
    public function __construct(protected RiskControlService $risk) {}

    // ============ 规则管理 ============

    public function indexRules(Request $request)
    {
        $rules = RiskRule::orderBy('priority')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $rules]);
    }

    public function storeRule(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:64|unique:risk_rules,code',
            'name'        => 'required|string|max:128',
            'type'        => 'required|in:register,login,payment,promotion,analysis,withdraw',
            'action'      => 'required|in:allow,deny,review',
            'conditions'  => 'required|array',
            'priority'    => 'nullable|integer',
            'enabled'     => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);
        $rule = RiskRule::create($data);
        $this->risk->flushRulesCache();
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $rule]);
    }

    public function updateRule(Request $request, int $id)
    {
        $rule = RiskRule::findOrFail($id);
        $data = $request->validate([
            'name'        => 'string|max:128',
            'action'      => 'in:allow,deny,review',
            'conditions'  => 'array',
            'priority'    => 'integer',
            'enabled'     => 'boolean',
            'description' => 'nullable|string',
        ]);
        $rule->update($data);
        $this->risk->flushRulesCache();
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $rule]);
    }

    public function destroyRule(int $id)
    {
        RiskRule::findOrFail($id)->delete();
        $this->risk->flushRulesCache();
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => null]);
    }

    // ============ 事件日志 ============

    public function indexEvents(Request $request)
    {
        $events = RiskEvent::with('user:id,nickname,mobile')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->risk_level, fn ($q) => $q->where('risk_level', $request->risk_level))
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $events]);
    }

    // ============ 黑名单 ============

    public function indexBlacklists(Request $request)
    {
        $items = RiskBlacklist::orderByDesc('id')
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $items]);
    }

    public function storeBlacklist(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:ip,mobile,device,user_id',
            'value'       => 'required|string|max:128',
            'reason'      => 'nullable|string',
            'ttl_seconds' => 'nullable|integer|min:60',
        ]);
        $bl = $this->risk->block(
            $data['type'], $data['value'],
            $data['reason'] ?? '', $request->user('admin')?->id,
            $data['ttl_seconds'] ?? null
        );
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $bl]);
    }

    public function destroyBlacklist(string $type, string $value)
    {
        $ok = $this->risk->unblock($type, $value);
        return response()->json(['code' => 0, 'message' => $ok ? '已解禁' : '未找到记录', 'data' => null]);
    }

    // ============ 统计 ============

    public function statistics()
    {
        $today = now()->startOfDay();
        $stats = [
            'today_events'    => RiskEvent::where('created_at', '>=', $today)->count(),
            'today_denied'    => RiskEvent::where('created_at', '>=', $today)->where('action', 'deny')->count(),
            'today_review'    => RiskEvent::where('created_at', '>=', $today)->where('action', 'review')->count(),
            'critical_events' => RiskEvent::where('risk_level', 'critical')->where('created_at', '>=', $today)->count(),
            'active_rules'    => RiskRule::where('enabled', true)->count(),
            'blacklist_total' => RiskBlacklist::count(),
            'by_type'         => RiskEvent::where('created_at', '>=', $today)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')->pluck('count', 'type')->toArray(),
        ];
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $stats]);
    }
}
