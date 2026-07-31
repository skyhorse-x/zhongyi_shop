<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmService
{
    /**
     * 获取当前配置的大模型参数
     */
    public function getConfig(): array
    {
        return [
            'provider' => SystemConfigService::get('llm_provider', 'openai'),
            'api_url' => SystemConfigService::get('llm_api_url', 'https://api.openai.com/v1'),
            'api_key' => SystemConfigService::get('llm_api_key', ''),
            'model' => SystemConfigService::get('llm_model', 'gpt-4o-mini'),
            'temperature' => (float) SystemConfigService::get('llm_temperature', 0.7),
            'max_tokens' => (int) SystemConfigService::get('llm_max_tokens', 2000),
            'timeout' => (int) SystemConfigService::get('llm_timeout', 30),
        ];
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
                'openai', 'deepseek', 'qwen' => $this->callOpenAiCompatible($config, $systemPrompt, $userMessage, $history),
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
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return [
                'success' => true,
                'content' => $content,
                'error' => '',
            ];
        }

        $errorMsg = $response->json('error.message') ?? $response->body();
        Log::error('OpenAI兼容接口调用失败', ['status' => $response->status(), 'error' => $errorMsg]);

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
        return $this->chat('You are a helpful assistant.', 'Hello, please reply with "API connection successful".');
    }
}
