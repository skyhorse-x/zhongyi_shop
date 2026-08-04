<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PromptController extends Controller
{
    /**
     * 获取所有提示词列表
     * GET /api/v1/admin/ai/prompts
     */
    public function index()
    {
        $prompts = Prompt::all();
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $prompts,
        ]);
    }

    /**
     * 更新提示词
     * PUT /api/v1/admin/ai/prompts/{id}
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'prompt' => 'required|string',
        ]);

        $prompt = Prompt::findOrFail($id);
        $prompt->prompt = $data['prompt'];
        $prompt->save();

        Log::info('Prompt updated', [
            'id' => $id,
            'type' => $prompt->type,
            'admin_id' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '提示词更新成功',
            'data' => $prompt,
        ]);
    }
}
