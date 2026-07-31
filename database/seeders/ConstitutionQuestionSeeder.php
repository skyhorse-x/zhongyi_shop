<?php

namespace Database\Seeders;

use App\Models\ConstitutionQuestion;
use Illuminate\Database\Seeder;

class ConstitutionQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            // 气虚质
            [
                'category' => '气虚质',
                'question' => '您是否容易感到疲乏？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 1,
            ],
            [
                'category' => '气虚质',
                'question' => '您是否容易气短，稍微活动就喘不上气？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 2,
            ],
            [
                'category' => '气虚质',
                'question' => '您是否容易出汗，即使轻微运动也会大汗淋漓？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 3,
            ],
            // 阳虚质
            [
                'category' => '阳虚质',
                'question' => '您是否经常感到手脚冰凉，怕冷？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 4,
            ],
            [
                'category' => '阳虚质',
                'question' => '您是否喜欢吃热食，不喜欢冷饮？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 5,
            ],
            [
                'category' => '阳虚质',
                'question' => '您是否经常腹泻，或者大便稀溏？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 6,
            ],
            // 阴虚质
            [
                'category' => '阴虚质',
                'question' => '您是否经常感到口干舌燥，想喝水？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 7,
            ],
            [
                'category' => '阴虚质',
                'question' => '您是否经常感到手脚心发热？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 8,
            ],
            [
                'category' => '阴虚质',
                'question' => '您是否经常失眠，或者睡眠质量不好？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 9,
            ],
            // 痰湿质
            [
                'category' => '痰湿质',
                'question' => '您是否容易感到身体沉重，疲倦乏力？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 10,
            ],
            [
                'category' => '痰湿质',
                'question' => '您是否容易出汗，且汗液黏腻？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 11,
            ],
            // 湿热质
            [
                'category' => '湿热质',
                'question' => '您是否经常感到口苦口干？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 12,
            ],
            [
                'category' => '湿热质',
                'question' => '您是否面部容易出油，长痘痘？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 13,
            ],
            // 血瘀质
            [
                'category' => '血瘀质',
                'question' => '您是否容易出现皮肤淤青？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 14,
            ],
            [
                'category' => '血瘀质',
                'question' => '您是否经常感到身体疼痛？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 15,
            ],
            // 气郁质
            [
                'category' => '气郁质',
                'question' => '您是否经常感到情绪低落，忧郁？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 16,
            ],
            [
                'category' => '气郁质',
                'question' => '您是否经常感到胸闷，喜欢叹气？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 17,
            ],
            // 特禀质
            [
                'category' => '特禀质',
                'question' => '您是否对某些食物或药物过敏？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 18,
            ],
            [
                'category' => '特禀质',
                'question' => '您是否容易打喷嚏、流鼻涕？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '从不', 'score' => 1],
                    ['value' => 'B', 'label' => '偶尔', 'score' => 2],
                    ['value' => 'C', 'label' => '经常', 'score' => 3],
                    ['value' => 'D', 'label' => '总是', 'score' => 4],
                ],
                'sort_order' => 19,
            ],
            // 平和质
            [
                'category' => '平和质',
                'question' => '您是否精力充沛，不容易感到疲劳？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '总是', 'score' => 1],
                    ['value' => 'B', 'label' => '经常', 'score' => 2],
                    ['value' => 'C', 'label' => '偶尔', 'score' => 3],
                    ['value' => 'D', 'label' => '从不', 'score' => 4],
                ],
                'sort_order' => 20,
            ],
            [
                'category' => '平和质',
                'question' => '您是否睡眠良好，醒来后精神饱满？',
                'type' => 'single',
                'options' => [
                    ['value' => 'A', 'label' => '总是', 'score' => 1],
                    ['value' => 'B', 'label' => '经常', 'score' => 2],
                    ['value' => 'C', 'label' => '偶尔', 'score' => 3],
                    ['value' => 'D', 'label' => '从不', 'score' => 4],
                ],
                'sort_order' => 21,
            ],
        ];

        foreach ($questions as $question) {
            ConstitutionQuestion::updateOrCreate(
                ['question' => $question['question']],
                $question
            );
        }
    }
}
