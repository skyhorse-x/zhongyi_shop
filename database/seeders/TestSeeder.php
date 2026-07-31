<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AiModel;
use App\Models\ConstitutionQuestion;
use App\Models\ProductPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 创建管理员
        Admin::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'name' => '超级管理员',
            ]
        );

        // 创建体质题目
        $questions = [
            [
                'category' => '气虚质',
                'question' => '您是否容易疲乏？',
                'type' => 'single',
                'options' => [
                    'A' => ['label' => '从不', 'scores' => ['pinghe' => 5, 'qixu' => 0]],
                    'B' => ['label' => '偶尔', 'scores' => ['pinghe' => 3, 'qixu' => 2]],
                    'C' => ['label' => '经常', 'scores' => ['pinghe' => 1, 'qixu' => 4]],
                    'D' => ['label' => '总是', 'scores' => ['pinghe' => 0, 'qixu' => 5]],
                ],
                'sort_order' => 1,
            ],
            [
                'category' => '气虚质',
                'question' => '您是否容易气短？',
                'type' => 'single',
                'options' => [
                    'A' => ['label' => '从不', 'scores' => ['pinghe' => 5, 'qixu' => 0]],
                    'B' => ['label' => '偶尔', 'scores' => ['pinghe' => 3, 'qixu' => 2]],
                    'C' => ['label' => '经常', 'scores' => ['pinghe' => 1, 'qixu' => 4]],
                    'D' => ['label' => '总是', 'scores' => ['pinghe' => 0, 'qixu' => 5]],
                ],
                'sort_order' => 2,
            ],
            [
                'category' => '阳虚质',
                'question' => '您是否畏寒怕冷？',
                'type' => 'single',
                'options' => [
                    'A' => ['label' => '从不', 'scores' => ['pinghe' => 5, 'yangxu' => 0]],
                    'B' => ['label' => '偶尔', 'scores' => ['pinghe' => 3, 'yangxu' => 2]],
                    'C' => ['label' => '经常', 'scores' => ['pinghe' => 1, 'yangxu' => 4]],
                    'D' => ['label' => '总是', 'scores' => ['pinghe' => 0, 'yangxu' => 5]],
                ],
                'sort_order' => 3,
            ],
            [
                'category' => '阴虚质',
                'question' => '您是否手足心热？',
                'type' => 'single',
                'options' => [
                    'A' => ['label' => '从不', 'scores' => ['pinghe' => 5, 'yinxu' => 0]],
                    'B' => ['label' => '偶尔', 'scores' => ['pinghe' => 3, 'yinxu' => 2]],
                    'C' => ['label' => '经常', 'scores' => ['pinghe' => 1, 'yinxu' => 4]],
                    'D' => ['label' => '总是', 'scores' => ['pinghe' => 0, 'yinxu' => 5]],
                ],
                'sort_order' => 4,
            ],
            [
                'category' => '痰湿质',
                'question' => '您是否腹部肥满？',
                'type' => 'single',
                'options' => [
                    'A' => ['label' => '从不', 'scores' => ['pinghe' => 5, 'tanshi' => 0]],
                    'B' => ['label' => '偶尔', 'scores' => ['pinghe' => 3, 'tanshi' => 2]],
                    'C' => ['label' => '经常', 'scores' => ['pinghe' => 1, 'tanshi' => 4]],
                    'D' => ['label' => '总是', 'scores' => ['pinghe' => 0, 'tanshi' => 5]],
                ],
                'sort_order' => 5,
            ],
        ];

        foreach ($questions as $q) {
            ConstitutionQuestion::create($q);
        }

        // 创建次数包
        ProductPackage::create([
            'name' => '舌诊单次',
            'type' => 'tongue',
            'times' => 1,
            'days' => 30,
            'price' => 9.9,
            'original_price' => 19.9,
            'is_recommend' => 0,
            'sort_order' => 1,
        ]);
        ProductPackage::create([
            'name' => '面诊单次',
            'type' => 'face',
            'times' => 1,
            'days' => 30,
            'price' => 9.9,
            'original_price' => 19.9,
            'is_recommend' => 0,
            'sort_order' => 2,
        ]);
        ProductPackage::create([
            'name' => '全套体验包',
            'type' => 'all',
            'times' => 5,
            'days' => 30,
            'price' => 29.9,
            'original_price' => 59.9,
            'is_recommend' => 1,
            'sort_order' => 3,
        ]);

        // 创建AI模型配置（api_key 留空，用户需在管理后台配置真实 API Key 后启用）
        AiModel::create([
            'name' => '豆包Vision（视觉分析）',
            'provider' => 'doubao',
            'model' => 'doubao-vision',
            'api_url' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
            'api_key' => '',
            'type' => 'vision',
            'analysis_type' => 'tongue',
            'is_enabled' => 0,
            'sort_order' => 1,
        ]);
        AiModel::create([
            'name' => 'DeepSeek（对话模型）',
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'api_url' => 'https://api.deepseek.com/v1/chat/completions',
            'api_key' => '',
            'type' => 'chat',
            'analysis_type' => 'qa',
            'is_enabled' => 0,
            'sort_order' => 2,
        ]);
    }
}
