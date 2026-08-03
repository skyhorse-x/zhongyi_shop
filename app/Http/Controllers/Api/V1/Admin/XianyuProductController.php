<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\XianyuProduct;
use Illuminate\Http\Request;

class XianyuProductController extends Controller
{
    /**
     * 后台：闲鱼商品列表（分页）
     */
    public function index(Request $request)
    {
        $query = XianyuProduct::query();

        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%' . $request->get('keyword') . '%');
        }

        $paginator = $query->orderBy('sort_order')->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $paginator,
        ]);
    }

    /**
     * 后台：新增闲鱼商品
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'link' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01|max:999999',
            'times' => 'nullable|integer|min:0|max:100000',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_enabled' => 'nullable|boolean',
        ]);

        $product = XianyuProduct::create([
            'title' => $validated['title'],
            'link' => $validated['link'],
            'amount' => $validated['amount'],
            'times' => $validated['times'] ?? 0,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_enabled' => $validated['is_enabled'] ?? true,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '添加成功',
            'data' => $product,
        ]);
    }

    /**
     * 后台：更新闲鱼商品
     */
    public function update(Request $request, int $id)
    {
        $product = XianyuProduct::find($id);

        if (!$product) {
            return response()->json([
                'code' => 404,
                'message' => '商品不存在',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'link' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01|max:999999',
            'times' => 'nullable|integer|min:0|max:100000',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_enabled' => 'nullable|boolean',
        ]);

        $product->update([
            'title' => $validated['title'],
            'link' => $validated['link'],
            'amount' => $validated['amount'],
            'times' => $validated['times'] ?? 0,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_enabled' => $validated['is_enabled'] ?? $product->is_enabled,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $product,
        ]);
    }

    /**
     * 后台：删除闲鱼商品
     */
    public function destroy(Request $request, int $id)
    {
        $product = XianyuProduct::find($id);

        if (!$product) {
            return response()->json([
                'code' => 404,
                'message' => '商品不存在',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }
}
