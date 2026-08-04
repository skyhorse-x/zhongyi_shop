<?php

namespace App\Services;

use App\Models\AiLog;
use App\Models\AiModel;
use Illuminate\Support\Facades\Crypt;
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
            $apiKey = $this->decryptApiKey($model->api_key ?? '') ?: SystemConfigService::get('llm_api_key', '');
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
            $apiKey = $this->decryptApiKey($model->api_key ?? '') ?: SystemConfigService::get('llm_api_key', '');
            $modelName = $model?->model ?? SystemConfigService::get('llm_model', 'doubao-vision');

            // 规范化图片列表（兼容单张字符串与多张数组）
            $imageUrls = is_array($imageUrl) ? $imageUrl : [$imageUrl];
            $content = [];
            foreach ($imageUrls as $url) {
                // 如果是本地URL，转为base64（外部AI服务无法访问内网地址）
                if ($this->isLocalUrl($url)) {
                    $base64Url = $this->convertImageToBase64($url);
                    if ($base64Url) {
                        $content[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $base64Url,
                            ],
                        ];
                        continue;
                    }
                    // Base64转换失败，跳过这张图片（不发送给AI）
                    Log::warning('Skip image due to base64 conversion failure', ['url' => $url]);
                    continue;
                }
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
            $apiKey = $this->decryptApiKey($model->api_key ?? '') ?: SystemConfigService::get('llm_api_key', '');
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
     * 获取舌诊分析提示词（优化版 - 降低诊断语气、增加评分依据和置信度）
     *
     * @return string
     */
    protected function getTongueAnalysisPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位专业的中医健康顾问，请根据提供的舌象图片（可能包含舌面、舌下等多张照片）进行中医舌诊分析。

【重要原则】
1. 舌诊仅作为传统健康分析参考，不能替代医生诊断
2. 使用"倾向""可能""表现"等描述，避免确定性诊断
3. 建议用"辅助调养""食疗参考"等非医疗用语
4. 强调日常保健，避免"治疗""改善疾病"等表述
{$this->userProfileContext($gender, $age)}

请严格按照以下格式输出分析结果：

## 健康评分：XX/100

评分依据（逐项列出，每项 +/- 分值）：
+ 舌色 XX +XX分
+ 舌苔 XX +XX分
- 舌形 XX -XX分
- 其他特征 XX -XX分

评分说明：根据舌象特征的润泽度、色泽、形态等综合评估。

---

## 一句话总结
用一句话（30字以内）概括当前舌象整体状态和主要关注点。

---

## 一、AI舌象观察

### AI识别置信度
- 舌色识别：XX%
- 舌形识别：XX%
- 舌苔识别：XX%
- 综合分析可信度：XX%

### 舌象特征
- 舌色：
- 舌形：
- 舌苔：
- 舌下络脉：

---

## 二、中医体质倾向分析

【重要】使用"倾向"描述，避免确定性诊断！

根据舌象特点分析：
- 舌象倾向：（如：脾虚湿盛倾向，兼有气阴不足表现）
- 相关证候：（如：可能存在脾胃运化功能偏弱倾向）

【说明】以上分析仅作为传统中医健康调理参考，不能替代医生诊断。

---

## 三、可能相关表现

如果平时出现以下表现，可进一步关注：
○ 容易疲劳
○ 饭后腹胀
○ 大便偏软
○ 睡眠不足
○ （根据辨证倾向列出3-5项相关症状）

---

## 四、日常调养建议

### 1. 饮食调养参考
日常食疗参考（非医疗建议）：
- 可适当食用：XX、XX、XX等
- 建议减少：XX、XX等
- 作为日常饮食选择，不宜替代治疗

### 2. 起居调摄
- 作息建议：
- 睡眠建议：

### 3. 运动建议
- 适合的运动方式：
- 运动频率：

### 4. 穴位保健参考
- 可参考的穴位：
- 按摩方法：

### 5. 情志调节
- 情绪管理建议：

---

## 五、温馨提示

【风险提示】
本报告基于舌象图片AI分析，仅用于传统健康管理参考。如有持续不适，请咨询专业医生。

【结合生活方式分析】
结合现代人常见生活方式（如饮食不规律、久坐、熬夜、压力较大等），分析可能影响健康的因素。

---

## 六、参考来源

【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述

例如：
- 《黄帝内经》："舌者，心之苗也"，舌象可反映脏腑气血盛衰
- 《伤寒论》：舌苔变化可反映外感病邪的进退
- 《濒湖脉学》：舌形舌色变化与体质倾向的关联

---

## 七、进一步了解（可选）

为了完善分析，您可以：
1. 补充是否有以下表现：
   □ 疲劳乏力 □ 大便偏稀 □ 睡眠不足 □ 食欲下降

2. 上传更多舌象照片：
   - 舌侧照片
   - 舌下络脉照片

AI将进一步完善分析。

---

【输出要求】
1. 总字数控制在1500字以内
2. 语气友好、专业但不过度医疗化
3. 避免使用"治疗""疾病""处方"等词汇
4. 强调"参考""倾向""辅助调养"等表述
PROMPT;
    }

    /**
     * 获取舌诊纯文本分析系统提示词（优化版 - 用户未上传图片）
     *
     * @return string
     */
    protected function getTongueTextSystemPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位专业的中医健康顾问，擅长根据用户口述的症状描述进行舌诊相关的中医辨证分析。

【重要原则】
1. 舌诊仅作为传统健康分析参考，不能替代医生诊断
2. 使用"倾向""可能""表现"等描述，避免确定性诊断
3. 建议用"辅助调养""食疗参考"等非医疗用语
4. 强调日常保健，避免"治疗""改善疾病"等表述

{$this->userProfileContext($gender, $age)}

【特别注意】
- 用户没有提供舌象图片，仅基于文字描述进行推断分析
- 需明确说明由于缺乏图片，分析可信度会降低
- 强烈建议用户在条件允许时拍照进行更精准的分析

请严格按照以下格式输出分析结果：

## 健康评分：XX/100

评分依据（逐项列出，每项 +/- 分值）：
+ 症状表现 XX +XX分
+ 描述清晰度 XX +XX分
- 症状倾向 XX -XX分
- 图片缺失影响 -XX分

评分说明：基于文字描述进行推断分析，评分仅供参考。

---

## 一句话总结
用一句话（30字以内）概括当前分析的主要结论。

---

## 一、AI分析说明

### 分析可信度
由于缺少舌象图片，本次分析完全基于您的文字描述进行推断：
- 文字描述可信度：XX%
- 综合分析可信度：XX%（图片分析通常更准确）

【重要提示】基于文字的分析准确性有限，建议上传舌象图片获得更精准的评估。

### 舌象推断
- 可能的舌色：
- 可能的舌形：
- 可能的舌苔：
- 推断依据：

---

## 二、中医体质倾向分析

【重要】使用"倾向"描述，避免确定性诊断！

根据您描述的症状分析：
- 舌象倾向：（如：可能与脾虚湿盛倾向相关）
- 相关证候：（如：可能存在脾胃功能偏弱倾向）

【说明】以上分析仅作为传统中医健康调理参考，不能替代医生诊断。

---

## 三、可能相关表现

如果平时出现以下表现，可进一步关注：
○ 容易疲劳
○ 饭后腹胀
○ 大便偏软
○ 睡眠不足
○ （根据描述列出3-5项相关症状）

---

## 四、日常调养建议

### 1. 饮食调养参考
日常食疗参考（非医疗建议）：
- 可适当食用：XX、XX、XX等
- 建议减少：XX、XX等
- 作为日常饮食选择，不宜替代治疗

### 2. 起居调摄
- 作息建议：
- 睡眠建议：

### 3. 运动建议
- 适合的运动方式：
- 运动频率：

### 4. 穴位保健参考
- 可参考的穴位：
- 按摩方法：

### 5. 情志调节
- 情绪管理建议：

---

## 五、温馨提示

【风险提示】
本报告基于文字描述AI分析，仅用于传统健康管理参考。如有持续不适，请咨询专业医生。

【建议】
建议在光线充足时拍摄舌象照片上传，以获得更精准的分析结果。

---

## 六、参考来源

【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述

---

## 七、进一步了解（可选）

为了完善分析，您可以：
1. 补充是否有以下表现：
   □ 疲劳乏力 □ 大便偏稀 □ 睡眠不足 □ 食欲下降

2. 上传舌象照片：
   - 舌面照片（正面）
   - 舌侧照片
   - 舌下络脉照片

AI将进一步完善分析。

---

【输出要求】
1. 总字数控制在1500字以内
2. 语气友好、专业但不过度医疗化
3. 避免使用"治疗""疾病""处方"等词汇
4. 强调"参考""倾向""辅助调养"等表述
5. 明确说明文字分析的局限性
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

## 参考来源
【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述
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

## 参考来源
【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述
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

    /**
     * 判断URL是否为本地地址
     */
    protected function isLocalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $localHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        return in_array($host, $localHosts) || str_starts_with($host, '192.168.') || str_starts_with($host, '10.');
    }

    /**
     * 将本地图片转为Base64 Data URI
     */
    protected function convertImageToBase64(string $url): ?string
    {
        try {
            // 从URL提取相对路径
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';

            // 移除 /storage/ 前缀，获取相对路径
            $relativePath = preg_replace('#^/storage/#', '', $path);
            if (empty($relativePath)) {
                return null;
            }

            // 构建本地文件路径
            $filePath = public_path('storage/' . $relativePath);
            if (!file_exists($filePath)) {
                Log::warning('Local image file not found', ['path' => $filePath, 'url' => $url]);
                return null;
            }

            // 读取文件并转为base64
            $imageData = file_get_contents($filePath);
            if ($imageData === false) {
                return null;
            }

            $mimeType = mime_content_type($filePath) ?: 'image/jpeg';
            return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
        } catch (\Throwable $e) {
            Log::error('Failed to convert image to base64', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 解密 API Key（数据库中加密存储）
     */
    protected function decryptApiKey(?string $encryptedKey): string
    {
        if (empty($encryptedKey)) {
            return '';
        }
        try {
            return Crypt::decryptString($encryptedKey);
        } catch (\Exception $e) {
            // 如果解密失败，可能是未加密的旧数据，直接返回
            return $encryptedKey;
        }
    }
}
