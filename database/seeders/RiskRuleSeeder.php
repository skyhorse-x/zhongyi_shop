<?php

namespace Database\Seeders;

use App\Models\RiskRule;
use Illuminate\Database\Seeder;

/**
 * 风控默认规则
 */
class RiskRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // 注册场景
            [
                'code' => 'register_ip_limit',
                'name' => '同 IP 1 小时注册超过 3 次',
                'type' => 'register',
                'action' => 'deny',
                'priority' => 10,
                'conditions' => ['dimension' => 'ip', 'window' => 3600, 'max_count' => 3],
                'description' => '防恶意批量注册',
            ],
            [
                'code' => 'register_mobile_limit',
                'name' => '同手机号 24 小时注册超过 3 次',
                'type' => 'register',
                'action' => 'deny',
                'priority' => 20,
                'conditions' => ['dimension' => 'mobile', 'window' => 86400, 'max_count' => 3],
                'description' => '防手机号被滥用',
            ],

            // 登录场景
            [
                'code' => 'login_ip_failed_limit',
                'name' => '同 IP 10 分钟登录失败 5 次',
                'type' => 'login',
                'action' => 'deny',
                'priority' => 10,
                'conditions' => ['dimension' => 'ip', 'window' => 600, 'max_count' => 5],
                'description' => '防暴力破解',
            ],

            // 支付场景
            [
                'code' => 'payment_ip_amount',
                'name' => '同 IP 1 小时支付超过 5000 元',
                'type' => 'payment',
                'action' => 'review',
                'priority' => 10,
                'conditions' => ['dimension' => 'ip', 'window' => 3600, 'max_count' => 5000],
                'description' => '大额支付人工审核',
            ],

            // 推广场景
            [
                'code' => 'promotion_invite_limit',
                'name' => '推广员 1 小时邀请超过 20 人',
                'type' => 'promotion',
                'action' => 'review',
                'priority' => 10,
                'conditions' => ['dimension' => 'user_id', 'window' => 3600, 'max_count' => 20],
                'description' => '防推广刷单',
            ],

            // AI 分析场景
            [
                'code' => 'analysis_user_limit',
                'name' => '单用户 1 小时分析超过 30 次',
                'type' => 'analysis',
                'action' => 'review',
                'priority' => 10,
                'conditions' => ['dimension' => 'user_id', 'window' => 3600, 'max_count' => 30],
                'description' => '防止 AI 接口被刷',
            ],

            // 提现场景
            [
                'code' => 'withdraw_user_limit',
                'name' => '单用户 24 小时提现超过 3 次',
                'type' => 'withdraw',
                'action' => 'deny',
                'priority' => 10,
                'conditions' => ['dimension' => 'user_id', 'window' => 86400, 'max_count' => 3],
                'description' => '防提现刷单',
            ],
        ];

        foreach ($rules as $rule) {
            RiskRule::updateOrCreate(['code' => $rule['code']], array_merge($rule, ['enabled' => true]));
        }
    }
}
