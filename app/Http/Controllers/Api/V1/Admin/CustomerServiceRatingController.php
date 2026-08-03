<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerServiceRating;
use Illuminate\Http\Request;

/**
 * 客服评价管理（管理端）
 */
class CustomerServiceRatingController extends Controller
{
    public function index(Request $request)
    {
        $list = CustomerServiceRating::with(['user:id,nickname', 'admin:id,name'])
            ->when($request->score, fn ($q) => $q->where('score', $request->score))
            ->when($request->admin_id, fn ($q) => $q->where('admin_id', $request->admin_id))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $list]);
    }

    public function statistics(Request $request)
    {
        $days = (int) $request->integer('days', 30);
        $since = now()->subDays($days);

        $base = CustomerServiceRating::where('created_at', '>=', $since);

        $stats = [
            'total'            => (clone $base)->count(),
            'avg_score'        => round((float) (clone $base)->avg('score'), 2),
            'score_distribution' => [
                '5' => (clone $base)->where('score', 5)->count(),
                '4' => (clone $base)->where('score', 4)->count(),
                '3' => (clone $base)->where('score', 3)->count(),
                '2' => (clone $base)->where('score', 2)->count(),
                '1' => (clone $base)->where('score', 1)->count(),
            ],
            'solved_rate'      => $this->calcPct((clone $base)->where('solved', 'yes')->count(), (clone $base)->count()),
            'good_rate'        => $this->calcPct((clone $base)->where('attitude', 'good')->count(), (clone $base)->count()),
        ];
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $stats]);
    }

    protected function calcPct(int $a, int $b): float
    {
        return $b > 0 ? round($a / $b * 100, 1) : 0.0;
    }
}
