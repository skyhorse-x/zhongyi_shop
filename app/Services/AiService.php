<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * 默认超时时间（秒）
     */
    protected int $timeout = 60;

    /**
     * 默认 API 地址
     */
    protected array $defaultApiUrls = [
        'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
        'doubao' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
        'openai' => 'https://api.openai.com/v1/chat/completions',
        'anthropic' => 'https://api.anthropic.com/v1/messages',
    ];

    public function __construct()
    {
        // 所有配置均从 ai_models 表读取，不再使用环境变量或 system_configs
    }

    /**
     * 舌诊分析（基于图片）
     *
     * @param array $imageUrls 舌象图片URL列表（可多张，如舌面、舌下）
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzeTongue(array $imageUrls, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting tongue analysis', ['image_urls' => $imageUrls]);

        $prompt = $this->getTongueAnalysisPrompt($gender, $age);

        try {
            // 优先使用豆包视觉模型
            $result = $this->callVisionApi($imageUrls, $prompt, 'tongue');

            Log::info('Tongue analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Tongue analysis failed', [
                'error' => $e->getMessage(),
                'image_urls' => $imageUrls,
            ]);
            throw $e;
        }
    }

    /**
     * 舌诊分析（基于文字描述，无图片）
     *
     * @param string $text 用户文字描述
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzeTongueByText(string $text, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting tongue analysis by text', ['text_length' => strlen($text)]);

        try {
            $result = $this->callTextApi($text, 'tongue', $gender, $age);

            Log::info('Tongue text analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Tongue text analysis failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 面诊分析（基于图片）
     *
     * @param string $imageUrl 面部图片URL
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzeFace(string $imageUrl, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting face analysis', ['image_url' => $imageUrl]);

        $prompt = $this->getFaceAnalysisPrompt($gender, $age);

        try {
            $result = $this->callVisionApi($imageUrl, $prompt, 'face');

            Log::info('Face analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Face analysis failed', [
                'error' => $e->getMessage(),
                'image_url' => $imageUrl,
            ]);
            throw $e;
        }
    }

    /**
     * 面诊分析（基于文字描述，无图片）
     *
     * @param string $text 用户文字描述
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzeFaceByText(string $text, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting face analysis by text', ['text_length' => strlen($text)]);

        try {
            $result = $this->callTextApi($text, 'face', $gender, $age);

            Log::info('Face text analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Face text analysis failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 健康问答
     *
     * @param string $message 用户消息
     * @param array $history 历史消息
     * @return string
     * @throws \Exception
     */
    public function chat(string $message, array $history = []): string
    {
        Log::info('Starting health QA', ['message' => $message]);

        $systemPrompt = $this->getHealthQaSystemPrompt();

        // 构建消息列表
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // 添加历史消息（最多10条）
        $history = array_slice($history, -10);
        foreach ($history as $item) {
            $messages[] = [
                'role' => $item['role'] ?? 'user',
                'content' => $item['content'] ?? '',
            ];
        }

        // 添加当前消息
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $result = $this->callChatApi($messages);

            Log::info('Health QA completed', [
                'response_length' => strlen($result),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Health QA failed', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
            throw $e;
        }
    }

    /**
     * 调用文本API（用于纯文本分析，无图片）
     *
     * @param string $userMessage 用户描述
     * @param string $type 分析类型 (tongue/face)
     * @return array
     * @throws \Exception
     */
    protected function callTextApi(string $userMessage, string $type, int $gender = 0, int $age = 0): array
    {
        // 获取AI模型配置（复用qa类型的对话模型）
        $model = AiModel::where('analysis_type', $type)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->first();

        // 如果没有针对该类型的模型配置，使用qa模型
        if (!$model) {
            $model = AiModel::where('analysis_type', 'qa')
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->first();
        }

        $startTime = microtime(true);

        try {
            $systemPrompt = $type === 'tongue'
                ? $this->getTongueTextSystemPrompt($gender, $age)
                : $this->getFaceTextSystemPrompt($gender, $age);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ];

            // 确定配置来源：ai_models 表优先，否则使用 system_configs
            $provider = $model?->provider ?? SystemConfigService::get('llm_provider', 'deepseek');
            $apiUrl = $model?->api_url ?? SystemConfigService::get('llm_api_url', $this->defaultApiUrls[$provider] ?? $this->defaultApiUrls['deepseek']);
            $apiKey = $model?->api_key ?? SystemConfigService::get('llm_api_key', '');
            $modelName = $model?->model ?? SystemConfigService::get('llm_model', 'deepseek-chat');

            $data = [
                'model' => $modelName,
                'messages' => $messages,
                'max_tokens' => 2000,
                'temperature' => 0.5,
            ];

            if (empty($apiKey)) {
                throw new \Exception('AI 模型未配置 API Key，请在管理后台 [系统设置] 或 [AI 管理] 中配置有效的 API Key');
            }

            $response = Http::withOptions(['verify' => false])->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post($apiUrl, $data);

            $duration = round((microtime(true) - $startTime) * 1000);

            // 记录API调用日志
            $this->logApiLog($model?->id, $type, $data, $response->json(), $duration, $response->successful());

            if (!$response->successful()) {
                throw new \Exception('AI 接口调用失败：HTTP ' . $response->status() . '，' . $this->extractApiError($response->body()));
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? '';

            return [
                'content' => $content,
                'usage' => $responseData['usage'] ?? [],
                'model' => $data['model'],
            ];
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiLog($model?->id, $type, $data ?? [], ['error' => $e->getMessage()], $duration, false);
            throw $e;
        }
    }

    /**
     * 调用视觉API（豆包）
     *
     * @param string|array $imageUrl 图片URL（单张字符串或多张数组）
     * @param string $prompt
     * @param string $type
     * @return array
     * @throws \Exception
     */
    protected function callVisionApi(string|array $imageUrl, string $prompt, string $type): array
    {
        // 获取AI模型配置：优先从 ai_models 表读取，如果没有则从 system_configs 读取
        $model = AiModel::where('analysis_type', $type)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->first();

        $startTime = microtime(true);

        try {
            // 确定配置来源：ai_models 表优先，否则使用 system_configs
            $provider = $model?->provider ?? SystemConfigService::get('llm_provider', 'doubao');
            $apiUrl = $model?->api_url ?? SystemConfigService::get('llm_api_url', $this->defaultApiUrls[$provider] ?? $this->defaultApiUrls['doubao']);
            $apiKey = $model?->api_key ?? SystemConfigService::get('llm_api_key', '');
            $modelName = $model?->model ?? SystemConfigService::get('llm_model', 'doubao-vision');

            // 规范化图片列表（兼容单张字符串与多张数组）
            $imageUrls = is_array($imageUrl) ? $imageUrl : [$imageUrl];
            $content = [];
            foreach ($imageUrls as $url) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $url,
                    ],
                ];
            }
            $content[] = [
                'type' => 'text',
                'text' => $prompt,
            ];

            // 构建请求数据
            $data = [
                'model' => $modelName,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
                'max_tokens' => 2000,
                'temperature' => 0.3,
            ];

            if (empty($apiKey)) {
                throw new \Exception('视觉分析模型未配置 API Key，请在管理后台 [系统设置] 或 [AI 管理] 中配置有效的 API Key');
            }

            $response = Http::withOptions(['verify' => false])->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post($apiUrl, $data);

            $duration = round((microtime(true) - $startTime) * 1000);

            // 记录API调用日志
            $this->logApiLog($model?->id, $type, $data, $response->json(), $duration, $response->successful());

            if (!$response->successful()) {
                throw new \Exception('视觉分析接口调用失败：HTTP ' . $response->status() . '，' . $this->extractApiError($response->body()));
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? '';

            return [
                'content' => $content,
                'usage' => $responseData['usage'] ?? [],
                'model' => $data['model'],
            ];
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiLog($model?->id, $type, $data ?? [], ['error' => $e->getMessage()], $duration, false);
            throw $e;
        }
    }

    /**
     * 调用对话API（DeepSeek）
     *
     * @param array $messages
     * @return string
     * @throws \Exception
     */
    protected function callChatApi(array $messages): string
    {
        // 获取AI模型配置：优先从 ai_models 表读取，如果没有则从 system_configs 读取
        $model = AiModel::where('analysis_type', 'qa')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->first();

        $startTime = microtime(true);

        try {
            // 确定配置来源：ai_models 表优先，否则使用 system_configs
            $provider = $model?->provider ?? SystemConfigService::get('llm_provider', 'deepseek');
            $apiUrl = $model?->api_url ?? SystemConfigService::get('llm_api_url', $this->defaultApiUrls[$provider] ?? $this->defaultApiUrls['deepseek']);
            $apiKey = $model?->api_key ?? SystemConfigService::get('llm_api_key', '');
            $modelName = $model?->model ?? SystemConfigService::get('llm_model', 'deepseek-chat');
            $maxTokens = $model?->max_tokens ?? (int) SystemConfigService::get('llm_max_tokens', 2000);
            $temperature = $model?->temperature ?? (float) SystemConfigService::get('llm_temperature', 0.7);

            $data = [
                'model' => $modelName,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ];

            if (empty($apiKey)) {
                throw new \Exception('AI 对话接口没有配置 API Key');
            }

            $response = Http::withOptions(['verify' => false])->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post($apiUrl, $data);

            $duration = round((microtime(true) - $startTime) * 1000);

            // 记录API调用日志
            $this->logApiLog($model?->id, 'qa', $data, $response->json(), $duration, $response->successful());

            if (!$response->successful()) {
                throw new \Exception('AI 对话接口调用失败：HTTP ' . $response->status() . '，' . $this->extractApiError($response->body()));
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? '';
            if (empty($content)) {
                throw new \Exception('AI 接口返回内容为空');
            }
            return $content;
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->logApiLog($model?->id, 'qa', $data ?? [], ['error' => $e->getMessage()], $duration, false);
            throw $e;
        }
    }

    /**
     * 解析 API 错误响应
     */
    protected function extractApiError(string $body): string
    {
        try {
            $data = json_decode($body, true);
            if (isset($data['error']['message'])) {
                return $data['error']['message'];
            }
            if (isset($data['message'])) {
                return $data['message'];
            }
        } catch (\Exception $e) {
            // 忽略 JSON 解析错误
        }
        return mb_substr($body, 0, 200);
    }

    /**
     * 记录API调用日志
     *
     * @param int|null $modelId
     * @param string $type
     * @param array $request
     * @param array $response
     * @param int $duration
     * @param bool $success
     * @return void
     */
    protected function logApiLog(?int $modelId, string $type, array $request, array $response, int $duration, bool $success): void
    {
        try {
            AiLog::create([
                'model_id' => $modelId,
                'type' => $type,
                'request' => json_encode($request),
                'response' => json_encode($response),
                'duration' => $duration,
                'status' => $success ? 1 : 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log AI API call', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取舌诊分析提示词
     *
     * @return string
     */
    protected function getTongueAnalysisPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医专家，请根据提供的舌象图片（可能包含舌面、舌下等多张照片）进行中医舌诊分析。
{$this->userProfileContext($gender, $age)}

请按照以下格式输出分析结果：

## 舌象观察
- 舌色：
- 舌形：
- 舌苔：
- 舌下络脉：

## 中医辨证
- 体质类型：
- 证候分析：

## 健康建议
- 饮食调理：
- 起居调摄：
- 运动建议：
- 穴位保健：

## 注意事项

请以专业、客观的态度进行分析，给出实用的调理建议。
PROMPT;
    }

    /**
     * 获取舌诊纯文本分析系统提示词（用户未上传图片）
     *
     * @return string
     */
    protected function getTongueTextSystemPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医专家，擅长根据用户口述的症状描述进行舌诊相关的中医辨证分析。
{$this->userProfileContext($gender, $age)}

注意：
- 用户没有提供舌象图片，请完全根据用户的文字描述进行推断分析
- 在"舌象观察"部分，需要说明由于缺乏图片，仅基于症状推断可能的舌象表现
- 不可给出具体疾病诊断，应建议用户在条件允许时拍照进行更精准的分析

请按照以下格式输出分析结果：

## 舌象推断
- 可能的舌色：
- 可能的舌形：
- 可能的舌苔：
- 推断依据：

## 中医辨证
- 体质类型：
- 证候分析：
- 可能涉及的脏腑：

## 健康建议
- 饮食调理：
- 起居调摄：
- 运动建议：
- 穴位保健：
- 情志调节：

## 温馨提示
- 建议在光线充足时拍摄舌象照片以获得更精准的分析
PROMPT;
    }

    /**
     * 获取面诊分析提示词
     *
     * @return string
     */
    protected function getFaceAnalysisPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医专家，请根据提供的面部图片进行中医面诊分析。
{$this->userProfileContext($gender, $age)}

请按照以下格式输出分析结果：

## 面部观察
- 面色：
- 光泽：
- 眼部：
- 鼻部：
- 唇部：

## 中医辨证
- 脏腑反映：
- 气血状态：
- 体质倾向：

## 健康建议
- 饮食调理：
- 起居调摄：
- 情志调节：
- 保健建议：

请以专业、客观的态度进行分析，给出实用的调理建议。
PROMPT;
    }

    /**
     * 获取面诊纯文本分析系统提示词（用户未上传图片）
     *
     * @return string
     */
    protected function getFaceTextSystemPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医专家，擅长根据用户口述的面部状况进行面诊相关的中医辨证分析。
{$this->userProfileContext($gender, $age)}

注意：
- 用户没有提供面部图片，请完全根据用户的文字描述进行推断分析
- 在"面部观察"部分，需要说明由于缺乏图片，仅基于描述推断可能的面部表现
- 不可给出具体疾病诊断，应建议用户在条件允许时拍照进行更精准的分析

请按照以下格式输出分析结果：

## 面部推断
- 可能的面色：
- 可能的眼部状态：
- 可能的唇色：
- 推断依据：

## 中医辨证
- 脏腑反映：
- 气血状态：
- 体质倾向：

## 健康建议
- 饮食调理：
- 起居调摄：
- 情志调节：
- 保健建议：
- 面部护理：

## 温馨提示
- 建议在自然光下拍摄正面面部照片以获得更精准的分析
PROMPT;
    }

    /**
     * 获取健康问答系统提示词
     *
     * @return string
     */
    protected function getHealthQaSystemPrompt(): string
    {
        return <<<PROMPT
你是一位专业的中医健康顾问，擅长运用中医理论为用户提供健康咨询和建议。

请遵循以下原则：
1. 基于中医理论进行辨证分析
2. 提供个性化的健康建议
3. 建议用户必要时就医诊治
4. 不做具体的疾病诊断和处方
5. 语言通俗易懂，专业准确

你可以回答的问题包括：
- 体质辨识与调理
- 饮食养生建议
- 四季养生方法
- 穴位保健知识
- 情志调节方法
- 运动养生指导
- 常见亚健康问题

请用简洁、专业的语言回答用户的问题。
PROMPT;
    }

    /**
     * 生成用户基本信息上下文（性别/年龄），供提示词注入
     *
     * @param int $gender 性别:1男 2女 0未知
     * @param int $age 年龄
     * @return string
     */
    protected function userProfileContext(int $gender = 0, int $age = 0): string
    {
        $parts = [];
        if ($gender === 1) {
            $parts[] = '性别：男';
        } elseif ($gender === 2) {
            $parts[] = '性别：女';
        }
        if ($age > 0 && $age < 150) {
            $parts[] = '年龄：' . $age . '岁';
        }
        if (empty($parts)) {
            return '';
        }
        return '用户基本信息：' . implode('，', $parts) . '。请在分析中结合用户的性别与年龄特点进行针对性辨证与建议。';
    }
}
