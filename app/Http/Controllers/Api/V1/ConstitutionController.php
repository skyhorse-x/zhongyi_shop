<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisTask;
use App\Models\ConstitutionQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ConstitutionController extends Controller
{
    /**
     * 获取体质测试题目
     */
    public function questions(Request $request)
    {
        $questions = ConstitutionQuestion::where('is_enabled', 1)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'category' => $q->category,
                    'question' => $q->question,
                    'type' => $q->type,
                    'options' => $q->options,
                ];
            });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $questions,
        ]);
    }

    /**
     * 提交体质测试答案
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 422,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // 计算体质得分
        $scores = $this->calculateScores($request->answers);
        $constitutionType = $this->getConstitutionType($scores);

        $task = AnalysisTask::create([
            'task_no' => 'CS' . date('Ymd') . Str::random(8),
            'user_id' => $request->user()->id,
            'type' => 'constitution',
            'status' => 2,
            'result' => [
                'scores' => $scores,
                'constitution_type' => $constitutionType,
                'summary' => "您的体质类型为{$constitutionType}...",
            ],
            'completed_at' => now(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '提交成功',
            'data' => [
                'task_no' => $task->task_no,
                'constitution_type' => $constitutionType,
                'scores' => $scores,
                'summary' => "您的体质类型为{$constitutionType}...",
                'is_paid' => false,
            ],
        ]);
    }

    /**
     * 获取体质测试报告
     */
    public function report(Request $request, string $taskNo)
    {
        $task = AnalysisTask::where('task_no', $taskNo)
            ->where('user_id', $request->user()->id)
            ->where('type', 'constitution')
            ->first();

        if (!$task) {
            return response()->json([
                'code' => 404,
                'message' => '报告不存在',
            ], 404);
        }

        $result = $task->result;
        $type = $result['constitution_type'];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'task_no' => $task->task_no,
                'constitution_type' => $type,
                'scores' => $result['scores'],
                'features' => $this->getConstitutionFeatures($type),
                'diet_advice' => $this->getConstitutionAdvice($type, 'diet'),
                'exercise_advice' => $this->getConstitutionAdvice($type, 'exercise'),
                'life_advice' => $this->getConstitutionAdvice($type, 'life'),
                'emotion_advice' => $this->getConstitutionAdvice($type, 'emotion'),
            ],
        ]);
    }

    /**
     * 计算体质得分
     */
    private function calculateScores(array $answers): array
    {
        $scores = [
            '平和质' => 0,
            '气虚质' => 0,
            '阳虚质' => 0,
            '阴虚质' => 0,
            '痰湿质' => 0,
            '湿热质' => 0,
            '血瘀质' => 0,
            '气郁质' => 0,
            '特禀质' => 0,
        ];

        foreach ($answers as $questionId => $optionValue) {
            $question = ConstitutionQuestion::find($questionId);
            if ($question) {
                // 找到对应的选项
                foreach ($question->options as $option) {
                    if ($option['value'] === $optionValue) {
                        $scores[$question->category] = ($scores[$question->category] ?? 0) + ($option['score'] ?? 0);
                        break;
                    }
                }
            }
        }

        return $scores;
    }

    /**
     * 获取体质类型
     */
    private function getConstitutionType(array $scores): string
    {
        $maxScore = max($scores);
        $type = array_search($maxScore, $scores);

        return $type ?: '平和质';
    }

    /**
     * 获取体质特征
     * 优先从数据库 system_configs 读取，配置缺失时使用默认值
     */
    private function getConstitutionFeatures(string $type): string
    {
        $configKey = 'constitution_feature_' . $type;
        $value = \App\Models\SystemConfig::where('key', $configKey)->value('value');

        if (!empty($value)) {
            return $value;
        }

        // 默认值（作为兜底，建议通过后台配置覆盖）
        $defaults = [
            '平和质' => '阴阳气血调和，体态适中，面色红润，精力充沛',
            '气虚质' => '元气不足，疲乏无力，气短懒言，易出汗',
            '阳虚质' => '阳气不足，畏寒怕冷，手足不温',
            '阴虚质' => '阴液亏少，口燥咽干，手足心热',
            '痰湿质' => '痰湿凝聚，形体肥胖，腹部肥满',
            '湿热质' => '湿热内蕴，面垢油腻，口苦苔黄腻',
            '血瘀质' => '血行不畅，肤色晦暗，舌质紫暗',
            '气郁质' => '气机郁滞，神情抑郁，忧虑脆弱',
            '特禀质' => '先天失常，生理缺陷，过敏反应',
        ];

        return $defaults[$type] ?? '';
    }

    /**
     * 获取体质调养建议（饮食/运动/起居/情志）
     * 优先从数据库读取，缺失时使用默认值
     */
    private function getConstitutionAdvice(string $type, string $category): string
    {
        $configKey = "constitution_{$category}_{$type}";
        $value = \App\Models\SystemConfig::where('key', $configKey)->value('value');

        if (!empty($value)) {
            return $value;
        }

        // 默认值
        $defaults = [
            'diet' => [
                '气虚质' => '宜食益气健脾食物，如黄豆、白扁豆、鸡肉、桂圆、大枣等',
                '阳虚质' => '宜食温阳食物，如羊肉、韭菜、生姜、桂圆等',
                '阴虚质' => '宜食滋阴润燥食物，如银耳、百合、梨、蜂蜜等',
                '痰湿质' => '宜食化痰利湿食物，如薏苡仁、冬瓜、荷叶、白萝卜等',
                '湿热质' => '宜食清热利湿食物，如绿豆、苦瓜、冬瓜、薏苡仁等',
                '血瘀质' => '宜食活血化瘀食物，如山楂、红花、玫瑰花、黑木耳等',
                '气郁质' => '宜食疏肝理气食物，如陈皮、玫瑰花、柑橘、洋葱等',
                '特禀质' => '宜食清淡平和食物，避免海鲜、辛辣等发物',
            ],
            'exercise' => [
                '气虚质' => '不宜剧烈运动，宜散步、慢跑、打太极拳',
                '阳虚质' => '宜在阳光充足时运动，避免大汗淋漓',
                '阴虚质' => '宜中等强度运动，避免过度出汗',
                '痰湿质' => '宜加强运动，循序渐进，以微微出汗为佳',
                '湿热质' => '宜大运动量锻炼，如跑步、游泳、爬山',
                '血瘀质' => '宜多运动促进血液循环，如舞蹈、步行',
                '气郁质' => '宜户外运动，舒展身心，如登山、骑行',
                '特禀质' => '宜温和运动，如瑜伽、太极、散步',
            ],
            'life' => [
                '气虚质' => '起居宜规律，避免熬夜和过度劳累，注意保暖',
                '阳虚质' => '冬季注意保暖，避免受寒，睡前可泡脚',
                '阴虚质' => '避免熬夜，保持环境湿润',
                '痰湿质' => '避免潮湿环境，衣着透气，忌久坐',
                '湿热质' => '避免炎热潮湿环境，保持皮肤清洁干燥',
                '血瘀质' => '避免久坐，注意保暖',
                '气郁质' => '起居规律，多接触阳光和自然',
                '特禀质' => '避开过敏源，保持环境清洁',
            ],
            'emotion' => [
                '气虚质' => '保持乐观心态，避免过度思虑和精神紧张',
                '阳虚质' => '保持心情愉悦，多听轻快音乐',
                '阴虚质' => '避免急躁恼怒，保持平静',
                '痰湿质' => '多参加社交活动，保持心情舒畅',
                '湿热质' => '避免烦躁易怒，保持平和心态',
                '血瘀质' => '培养兴趣爱好，舒缓情绪',
                '气郁质' => '多与人交流，倾诉心事，避免独处',
                '特禀质' => '保持情绪稳定，避免过度紧张',
            ],
        ];

        return $defaults[$category][$type] ?? '请咨询专业医师获取个性化建议';
    }
}
