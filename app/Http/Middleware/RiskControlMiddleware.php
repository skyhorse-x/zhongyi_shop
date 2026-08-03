<?php

namespace App\Http\Middleware;

use App\Services\RiskControlService;
use Closure;
use Illuminate\Http\Request;

/**
 * 风控中间件
 *
 * 用法：在路由中 ->middleware('risk:register') 或 ->middleware('risk:payment')
 *
 * 自动从 Request 中提取 IP、user_id、mobile 等上下文。
 */
class RiskControlMiddleware
{
    public function __construct(protected RiskControlService $risk) {}

    public function handle(Request $request, Closure $next, string $type)
    {
        $context = [
            'user_id' => $request->user()?->id,
            'ip'      => $request->ip(),
            'mobile'  => $request->input('mobile'),
            'device_id' => $request->header('X-Device-Id'),
        ];

        $result = $this->risk->check($type, $context);

        if ($result['action'] === 'deny') {
            return response()->json([
                'code'    => 429,
                'message' => $result['message'],
                'data'    => null,
            ], 429);
        }

        if ($result['action'] === 'review') {
            $request->attributes->set('risk_review', true);
            $request->attributes->set('risk_reason', $result['reason']);
        }

        return $next($request);
    }
}
