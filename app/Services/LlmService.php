<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SystemConfigService;

class LlmService
{
    /**
     * 获取当前配置的大模型参数
     *
     * 配置来源：完全由后台管理（system_configs 表）控制。
     * .env 中不保留任何 LLM 相关变量，避免 key 泄露到代码仓库。
     *
     * 若 SystemConfig 中不存在某项则使用代码内置的兜底默认值（仅占位符，不会被实际使用）。
     */
    public function getConfig(): array
    {
        return [
            'provider'    => self::resolveConfig('llm_provider',    'openai'),
            'api_url'     => self::resolveConfig('llm_api_url',     'https://api.openai.com/v1'),
            'api_key'     => self::resolveConfig('llm_api_key',     ''),
            'model'       => self::resolveConfig('llm_model',       'gpt-4o-mini'),
            'temperature' => (float) self::resolveConfig('llm_temperature', '0.7'),
            'max_tokens'  => (int)   self::resolveConfig('llm_max_tokens',  '2000'),
            'timeout'     => (int)   self::resolveConfig('llm_timeout',     '30'),
        ];
    }

    /**
     * 读取 SystemConfig 配置；若表中不存在/为空则 fallback 到兜底默认值
     */
    private static function resolveConfig(string $key, string $fallback): string
    {
        $value = SystemConfigService::get($key, null);
        if ($value === null || $value === '') {
            return $fallback;
        }
        return (string) $value;
    }

    /**
     * 检查API密钥是否已配置
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        return !empty($config['api_key']);
    }

    /**
     * 调用大模型对话
     *
     * @param string $systemPrompt 系统提示词
     * @param string $userMessage 用户消息
     * @param array $history 历史消息 [['role' => 'user', 'content' => '...'], ...]
     * @return array ['success' => bool, 'content' => string, 'error' => string]
     */
    public function chat(string $systemPrompt, string $userMessage, array $history = []): array
    {
        $config = $this->getConfig();

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'content' => '',
                'error' => '大模型API密钥未配置，请在系统设置中配置',
            ];
        }

        try {
            $provider = $config['provider'];

            return match ($provider) {
                'openai', 'deepseek', 'qwen', 'longcat' => $this->callOpenAiCompatible($config, $systemPrompt, $userMessage, $history),
                'anthropic' => $this->callAnthropic($config, $systemPrompt, $userMessage, $history),
                default => [
                    'success' => false,
                    'content' => '',
                    'error' => "不支持的大模型服务商: {$provider}",
                ],
            };
        } catch (\Exception $e) {
            Log::error('大模型调用失败: ' . $e->getMessage(), [
                'provider' => $config['provider'],
                'model' => $config['model'],
            ]);

            return [
                'success' => false,
                'content' => '',
                'error' => '大模型调用失败: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 调用 OpenAI 兼容接口（OpenAI、DeepSeek、通义千问等）
     */
    private function callOpenAiCompatible(array $config, string $systemPrompt, string $userMessage, array $history): array
    {
        // 构建消息列表
        $messages = [];

        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        // 添加历史消息
        foreach ($history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        // 添加当前用户消息
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $url = rtrim($config['api_url'], '/') . '/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout($config['timeout'])->post($url, [
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => $config['temperature'],
            'max_tokens' => $config['max_tokens'],
            'stream' => false,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $choice = $data['choices'][0] ?? [];
            $message = $choice['message'] ?? [];

            // 优先取 content；若 LongCat 仅返回 reasoning_content（深思考模型），则拼接为最终回答
            $content = $message['content'] ?? '';
            $reasoning = $message['reasoning_content'] ?? '';
            if ($content === '' && $reasoning !== '') {
                $content = $reasoning;
            } elseif ($content !== '' && $reasoning !== '' && $reasoning !== $content) {
                // 同时返回时，保留 thinking 过程便于诊断
                $content = "[思考过程]\n" . $reasoning . "\n\n[最终回答]\n" . $content;
            }

            return [
                'success' => true,
                'content' => $content,
                'error' => '',
                'usage' => $data['usage'] ?? [],
            ];
        }

        $errorMsg = $response->json('error.message') ?? $response->body();
        Log::error('OpenAI兼容接口调用失败', [
            'provider' => $config['provider'],
            'url' => $url,
            'status' => $response->status(),
            'error' => $errorMsg,
        ]);

        return [
            'success' => false,
            'content' => '',
            'error' => "API调用失败: {$errorMsg}",
        ];
    }

    /**
     * 调用 Anthropic Claude 接口
     */
    private function callAnthropic(array $config, string $systemPrompt, string $userMessage, array $history): array
    {
        // 构建消息列表
        $messages = [];

        // 添加历史消息
        foreach ($history as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        // 添加当前用户消息
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $url = rtrim($config['api_url'], '/') . '/v1/messages';

        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->timeout($config['timeout'])->post($url, [
            'model' => $config['model'],
            'system' => $systemPrompt,
            'messages' => $messages,
            'temperature' => $config['temperature'],
            'max_tokens' => $config['max_tokens'],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = '';
            foreach ($data['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                }
            }

            return [
                'success' => true,
                'content' => $content,
                'error' => '',
            ];
        }

        $errorMsg = $response->json('error.message') ?? $response->body();
        Log::error('Anthropic接口调用失败', ['status' => $response->status(), 'error' => $errorMsg]);

        return [
            'success' => false,
            'content' => '',
            'error' => "API调用失败: {$errorMsg}",
        ];
    }

    /**
     * 测试API连接
     */
    public function testConnection(): array
    {
        return $this->chat(
            '你是一个测试助手。',
            '你好，请用一句话确认连接成功，例如："LongCat 连接成功！"',
        );
    }

    /**
     * 列出已配置的供应商清单（用于后台 UI）
     */
    public static function getSupportedProviders(): array
    {
        return [
            ['value' => 'openai',    'label' => 'OpenAI',           'baseUrl' => 'https://api.openai.com/v1'],
            ['value' => 'anthropic', 'label' => 'Anthropic Claude', 'baseUrl' => 'https://api.anthropic.com'],
            ['value' => 'deepseek',  'label' => 'DeepSeek',         'baseUrl' => 'https://api.deepseek.com/v1'],
            ['value' => 'qwen',      'label' => '通义千问',         'baseUrl' => 'https://dashscope.aliyuncs.com/compatible-mode/v1'],
            ['value' => 'longcat',   'label' => '美团 LongCat',     'baseUrl' => 'https://api.longcat.chat/openai/v1'],
        ];
    }
}
