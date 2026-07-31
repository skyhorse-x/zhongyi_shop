<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\AiModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * DeepSeek API 配置
     */
    protected array $deepseekConfig;

    /**
     * 豆包 API 配置
     */
    protected array $doubaoConfig;

    /**
     * 默认超时时间（秒）
     */
    protected int $timeout = 60;

    public function __construct()
    {
        // AI 配置从后台「系统设置」中读取，键：ai_deepseek_api_key, ai_doubao_api_key
        $this->deepseekConfig = [
            'api_key' => \App\Models\SystemConfig::where('key', 'ai_deepseek_api_key')->value('value') ?: '',
            'api_url' => 'https://api.deepseek.com/v1/chat/completions',
        ];

        $this->doubaoConfig = [
            'api_key' => \App\Models\SystemConfig::where('key', 'ai_doubao_api_key')->value('value') ?: '',
            'api_url' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
        ];
    }

    /**
     * 舌诊分析（基于图片）
     *
     * @param string $imageUrl 舌象图片URL
     * @return array
     * @throws \Exception
     */
    public function analyzeTongue(string $imageUrl): array
    {
        Log::info('Starting tongue analysis', ['image_url' => $imageUrl]);

        $prompt = $this->getTongueAnalysisPrompt();

        try {
            // 优先使用豆包视觉模型
            $result = $this->callVisionApi($imageUrl, $prompt, 'tongue');

            Log::info('Tongue analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Tongue analysis failed', [
                'error' => $e->getMessage(),
                'image_url' => $imageUrl,
            ]);
            throw $e;
        }
    }

    /**
     * 舌诊分析（基于文字描述，无图片）
     *
     * @param string $text 用户文字描述
     * @return array
     * @throws \Exception
     */
    public function analyzeTongueByText(string $text): array
    {
        Log::info('Starting tongue analysis by text', ['text_length' => strlen($text)]);

        $prompt = $this->getTongueTextSystemPrompt($text);

        try {
            $result = $this->callTextApi($prompt, 'tongue');

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
     * @return array
     * @throws \Exception
     */
    public function analyzeFace(string $imageUrl): array
    {
        Log::info('Starting face analysis', ['image_url' => $imageUrl]);

        $prompt = $this->getFaceAnalysisPrompt();

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
     * @return array
     * @throws \Exception
     */
    public function analyzeFaceByText(string $text): array
    {
        Log::info('Starting face analysis by text', ['text_length' => strlen($text)]);

        try {
            $result = $this->callTextApi($text, 'face');

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
    protected function callTextApi(string $userMessage, string $type): array
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
                ? $this->getTongueTextSystemPrompt()
                : $this->getFaceTextSystemPrompt();

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ];

            $data = [
                'model' => $model?->model ?? 'deepseek-chat',
                'messages' => $messages,
                'max_tokens' => 2000,
                'temperature' => 0.5,
            ];

            // 优先使用专属模型配置，否则按类型选用 DeepSeek
            $apiUrl = $model?->api_url ?? $this->deepseekConfig['api_url'];
            $apiKey = $model?->api_key ?? $this->deepseekConfig['api_key'];

            if (empty($apiKey)) {
                throw new \Exception('AI 模型未配置 API Key，请在管理后台 [AI 管理] 中配置有效的 API Key');
            }

            $response = Http::withHeaders([
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
     * @param string $imageUrl
     * @param string $prompt
     * @param string $type
     * @return array
     * @throws \Exception
     */
    protected function callVisionApi(string $imageUrl, string $prompt, string $type): array
    {
        // 获取AI模型配置
        $model = AiModel::where('analysis_type', $type)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->first();

        $startTime = microtime(true);

        try {
            // 构建请求数据
            $data = [
                'model' => $model?->model ?? 'doubao-vision',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $imageUrl,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 2000,
                'temperature' => 0.3,
            ];

            $apiUrl = $model?->api_url ?? $this->doubaoConfig['api_url'];
            $apiKey = $model?->api_key ?? $this->doubaoConfig['api_key'];

            if (empty($apiKey)) {
                throw new \Exception('视觉分析模型未配置 API Key，请在管理后台 [AI 管理] 中配置有效的 API Key');
            }

            $response = Http::withHeaders([
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
        // 获取AI模型配置
        $model = AiModel::where('analysis_type', 'qa')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->first();

        $startTime = microtime(true);

        try {
            $data = [
                'model' => $model?->model ?? 'deepseek-chat',
                'messages' => $messages,
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ];

            $apiUrl = $model?->api_url ?? $this->deepseekConfig['api_url'];
            $apiKey = $model?->api_key ?? $this->deepseekConfig['api_key'];

            if (empty($apiKey)) {
                throw new \Exception('AI 对话模型未配置 API Key，请在管理后台 [AI 管理] 中配置有效的 API Key');
            }

            $response = Http::withHeaders([
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
    protected function getTongueAnalysisPrompt(): string
    {
        return <<<PROMPT
你是一位资深的中医专家，请根据提供的舌象图片进行中医舌诊分析。

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
    protected function getTongueTextSystemPrompt(): string
    {
        return <<<PROMPT
你是一位资深的中医专家，擅长根据用户口述的症状描述进行舌诊相关的中医辨证分析。

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
    protected function getFaceAnalysisPrompt(): string
    {
        return <<<PROMPT
你是一位资深的中医专家，请根据提供的面部图片进行中医面诊分析。

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
    protected function getFaceTextSystemPrompt(): string
    {
        return <<<PROMPT
你是一位资深的中医专家，擅长根据用户口述的面部状况进行面诊相关的中医辨证分析。

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
}
