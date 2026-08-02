<?php

namespace Database\Seeders;

use App\Models\CustomerServiceConfig;
use App\Models\CustomerServicePhrase;
use Illuminate\Database\Seeder;

class CustomerServiceSeeder extends Seeder
{
    /**
     * 初始化客服系统数据
     */
    public function run(): void
    {
        // 客服配置
        CustomerServiceConfig::updateOrCreate(
            ['key' => 'welcome_message'],
            [
                'value' => '您好！欢迎使用AI中医健康平台，我是您的专属客服。请问有什么可以帮助您的吗？您可以咨询健康问题、了解套餐服务或反馈使用体验。',
                'name' => '欢迎消息',
                'remark' => '用户进入客服时自动发送的欢迎消息',
            ]
        );

        CustomerServiceConfig::updateOrCreate(
            ['key' => 'auto_welcome'],
            [
                'value' => 'true',
                'name' => '自动欢迎',
                'remark' => '是否自动发送欢迎消息',
            ]
        );

        // 默认常用话术（公共话术）
        $phrases = [
            // 问候语
            [
                'admin_id' => 0,
                'title' => '欢迎问候',
                'content' => '您好！欢迎使用AI中医健康平台，我是您的专属客服。请问有什么可以帮助您的吗？',
                'category' => 'greeting',
                'sort_order' => 1,
                'is_public' => true,
            ],
            [
                'admin_id' => 0,
                'title' => '结束语',
                'content' => '感谢您的咨询，祝您身体健康！如有其他问题随时联系我。',
                'category' => 'greeting',
                'sort_order' => 2,
                'is_public' => true,
            ],
            // 常见问题
            [
                'admin_id' => 0,
                'title' => '如何购买套餐',
                'content' => "您可以通过以下步骤购买套餐：\n1. 点击【购买套餐】\n2. 选择合适的套餐\n3. 确认支付\n\n购买后立即生效，可以在个人中心查看剩余次数。",
                'category' => 'common',
                'sort_order' => 1,
                'is_public' => true,
            ],
            [
                'admin_id' => 0,
                'title' => '如何查看分析报告',
                'content' => "您可以在【个人中心】-【我的报告】中查看历史分析报告。每份报告都会详细记录您的健康数据和建议。",
                'category' => 'common',
                'sort_order' => 2,
                'is_public' => true,
            ],
            [
                'admin_id' => 0,
                'title' => '余额不足怎么办',
                'content' => "您的余额不足，可以通过以下方式充值：\n1. 购买次数包（推荐，性价比更高）\n2. 单次充值\n\n点击【立即充值】即可操作。",
                'category' => 'common',
                'sort_order' => 3,
                'is_public' => true,
            ],
            [
                'admin_id' => 0,
                'title' => '如何邀请好友',
                'content' => "您可以通过【推广中心】生成专属邀请海报，邀请好友注册成功后，您将获得额外次数奖励！",
                'category' => 'promotion',
                'sort_order' => 1,
                'is_public' => true,
            ],
        ];

        foreach ($phrases as $phrase) {
            CustomerServicePhrase::updateOrCreate(
                ['title' => $phrase['title']],
                $phrase
            );
        }
    }
}
