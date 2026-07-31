<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * 获取文章列表
     */
    public function index(Request $request)
    {
        $articles = Article::where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 10));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $articles,
        ]);
    }

    /**
     * 获取文章详情
     */
    public function detail(Request $request, int $id)
    {
        $article = Article::where('status', 1)->find($id);

        if (!$article) {
            return response()->json([
                'code' => 404,
                'message' => '文章不存在',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $article,
        ]);
    }
}
