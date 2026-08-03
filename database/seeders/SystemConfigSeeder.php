<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;

class SystemConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configs = [
            // 基本设置
            ['key' => 'site_name', 'value' => 'AI中医健康管理', 'name' => '站点名称', 'group_name' => 'basic', 'type' => 'text', 'remark' => '网站显示的名称'],
            ['key' => 'site_description', 'value' => '智能分析 · 科学养生 · 守护健康', 'name' => '站点描述', 'group_name' => 'basic', 'type' => 'textarea', 'remark' => '网站描述信息'],
            ['key' => 'admin_email', 'value' => 'admin@tcm.com', 'name' => '管理员邮箱', 'group_name' => 'basic', 'type' => 'text', 'remark' => '接收系统通知的邮箱'],

            // 费用设置
            ['key' => 'analysis_mode', 'value' => 'paid', 'name' => '分析模式', 'group_name' => 'fee', 'type' => 'select', 'remark' => '付费分析或免费分析'],
            ['key' => 'analysis_price', 'value' => '9.99', 'name' => '单次分析价格', 'group_name' => 'fee', 'type' => 'number', 'remark' => '用户单次AI分析收费（元）'],
            ['key' => 'ai_cost_per_time', 'value' => '0.05', 'name' => 'AI分析成本', 'group_name' => 'fee', 'type' => 'number', 'remark' => '每次AI调用成本（元）'],

            // 推广返利设置
            ['key' => 'commission_rate', 'value' => '15', 'name' => '推广佣金比例', 'group_name' => 'promotion', 'type' => 'number', 'remark' => '推广员佣金百分比（%）'],
            ['key' => 'commission_min_amount', 'value' => '1', 'name' => '最低佣金金额', 'group_name' => 'promotion', 'type' => 'number', 'remark' => '最低产生佣金的订单金额（元）'],
            ['key' => 'commission_settle_days', 'value' => '7', 'name' => '佣金结算天数', 'group_name' => 'promotion', 'type' => 'number', 'remark' => '订单完成后几天结算佣金'],
            ['key' => 'withdraw_min_amount', 'value' => '10', 'name' => '最低提现金额', 'group_name' => 'promotion', 'type' => 'number', 'remark' => '推广员最低可提现金额（元）'],

            // 大模型接口配置（由后台完全接管，不再依赖 .env）
            ['key' => 'llm_provider', 'value' => 'longcat', 'name' => '大模型服务商', 'group_name' => 'llm', 'type' => 'select', 'remark' => '选择大模型服务商：openai/anthropic/deepseek/qwen/longcat（美团 LongCat）'],
            ['key' => 'llm_api_url', 'value' => 'https://api.longcat.chat/openai/v1', 'name' => 'API地址', 'group_name' => 'llm', 'type' => 'text', 'remark' => '大模型API请求地址（OpenAI 兼容，如 LongCat: https://api.longcat.chat/openai/v1）'],
            ['key' => 'llm_api_key', 'value' => '', 'name' => 'API密钥', 'group_name' => 'llm', 'type' => 'password', 'remark' => '大模型API密钥（LongCat 使用 ak_ 前缀；首次部署请在后台填写）'],
            ['key' => 'llm_model', 'value' => 'LongCat-2.0', 'name' => '模型名称', 'group_name' => 'llm', 'type' => 'text', 'remark' => '使用的模型名称（LongCat 可选：LongCat-2.0 / LongCat-Flash-Chat）'],
            ['key' => 'llm_temperature', 'value' => '0.7', 'name' => '温度参数', 'group_name' => 'llm', 'type' => 'number', 'remark' => '生成文本的随机性（0-2）'],
            ['key' => 'llm_max_tokens', 'value' => '2000', 'name' => '最大Token数', 'group_name' => 'llm', 'type' => 'number', 'remark' => '单次请求最大token数'],
            ['key' => 'llm_timeout', 'value' => '30', 'name' => '超时时间', 'group_name' => 'llm', 'type' => 'number', 'remark' => 'API请求超时时间（秒）'],

            // 微信配置
            ['key' => 'wechat_appid', 'value' => '', 'name' => '微信小程序AppID', 'group_name' => 'wechat', 'type' => 'text', 'remark' => '微信小程序AppID'],
            ['key' => 'wechat_secret', 'value' => '', 'name' => '微信小程序Secret', 'group_name' => 'wechat', 'type' => 'password', 'remark' => '微信小程序Secret'],
            ['key' => 'wechat_mch_id', 'value' => '', 'name' => '微信支付商户号', 'group_name' => 'wechat', 'type' => 'text', 'remark' => '微信支付商户号'],
            ['key' => 'wechat_pay_key', 'value' => '', 'name' => '微信支付API密钥', 'group_name' => 'wechat', 'type' => 'password', 'remark' => '微信支付API密钥'],
        ];

        foreach ($configs as $config) {
            SystemConfig::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
