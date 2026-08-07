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
            $systemPrompt = match($type) {
                'tongue' => $this->getTongueTextSystemPrompt($gender, $age),
                'face' => $this->getFaceTextSystemPrompt($gender, $age),
                'palm' => $this->getPalmTextSystemPrompt($gender, $age),
                'eye' => $this->getEyeTextSystemPrompt($gender, $age),
                default => $this->getTongueTextSystemPrompt($gender, $age),
            };

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
                // 清理 URL：去除首尾空格和特殊字符
                $url = trim($url, " \t\n\r\0\x0B`\"'");
                
                // 验证 URL 格式
                if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    Log::warning('Invalid image URL skipped', ['url' => $url]);
                    continue;
                }
                
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
                    // Base64转换失败，尝试将本地URL转换为生产URL
                    $productionUrl = $this->convertLocalUrlToProduction($url);
                    if ($productionUrl !== $url) {
                        Log::info('Converted local URL to production URL', [
                            'original' => $url,
                            'converted' => $productionUrl,
                        ]);
                        $url = $productionUrl;
                        // 继续执行下面的可访问性检查和发送逻辑
                    } else {
                        // 无法转换，跳过这张图片
                        Log::warning('Skip image due to base64 conversion failure and no production URL', ['url' => $url]);
                        continue;
                    }
                }
                
                // 检查远程图片URL是否可访问
                if (!$this->isImageUrlAccessible($url)) {
                    throw new \Exception("图片URL无法访问或下载超时：{$url}，请检查图片是否存在或稍后重试");
                }
                
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $url,
                    ],
                ];
            }
            
            // 检查是否有有效的图片
            if (empty($content)) {
                throw new \Exception('没有有效的图片可供分析，请检查图片URL是否正确');
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
    protected function logApiLog(?int $modelId, string $type, array $request, ?array $response, int $duration, bool $success): void
    {
        try {
            AiLog::create([
                'model_id' => $modelId,
                'type' => $type,
                'request' => json_encode($request),
                'response' => $response ? json_encode($response) : null,
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
- 作息建议：XXX（具体内容，如：避免熬夜，尽量23点前入睡）
- 睡眠建议：XXX（具体内容）

### 3. 运动建议
- 适合的运动方式：XXX（具体内容，如：散步、八段锦、太极拳等）
- 运动频率：XXX（具体内容）

### 4. 穴位保健参考
- 可参考的穴位：XXX（具体内容，如：足三里、关元、气海等）
- 按摩方法：XXX（具体内容）

### 5. 情志调节
- 情绪管理建议：XXX（具体内容，如：保持心情舒畅，避免情绪波动过大）

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
5. **每项调养建议必须有具体内容，不能留空**
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
每项建议必须有具体内容，不能留空：
- 饮食调理：XXX（具体内容，如：可适当食用山药、茯苓、莲子等健脾祛湿食物）
- 起居调摄：XXX（具体内容，如：避免熬夜，保持规律作息）
- 情志调节：XXX（具体内容，如：保持心情舒畅，适当进行放松活动）
- 保健建议：XXX（具体内容，如：可按摩足三里、关元等穴位）

请以专业、客观的态度进行分析，给出实用的调理建议。

## 参考来源
【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述
PROMPT;
    }

    /**
     * 获取手相分析系统提示词（文字描述模式）
     * 基于中医手诊理论，通过手掌特征分析健康状况
     *
     * @param int $gender 性别 1男 2女
     * @param int $age 年龄
     * @return string
     */
    protected function getPalmTextSystemPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医手诊专家，擅长根据用户的手掌特征进行中医健康分析。

【重要原则】
1. 基于中医手诊理论，通过手掌形态、色泽、纹理等特征分析健康状况
2. 中医手诊属于传统医学范畴，分析结果仅供参考健康管理
3. 使用"倾向""可能""提示"等描述，避免确定性诊断
4. 强调"治未病"理念，注重健康预防和调理
5. 如有明显健康问题，建议用户及时就医检查

{$this->userProfileContext($gender, $age)}

【中医手诊分析维度】
请从以下中医手诊角度进行分析：

一、手掌形态分析
- 手掌厚薄、大小、肌肉丰满度
- 手掌色泽（红润、苍白、潮红、暗紫等）
- 手掌温度、湿度（寒热虚实判断）
- 对应脏腑：脾胃、气血状态

二、手指形态分析
- 手指粗细、长短比例
- 指甲色泽、月牙、纹路
- 五指对应五脏（拇指-脾、食指-肝、中指-心、无名指-肺、小指-肾）
- 指节形态与健康关联

三、主要掌纹健康分析
- 生命线：生命力、先天体质、肾精状态
- 智慧线：心脑血管、神经系统
- 感情线：心脏功能、情绪状态
- 健康线：免疫系统与整体健康
- 掌纹清晰度、深浅、断裂的健康提示

四、手掌分区与脏腑对应
- 八卦分区：乾、坎、艮、震、巽、离、坤、兑
- 掌丘分析：金星丘、木星丘、土星丘等
- 脏腑在手掌的投影区域

请严格按照以下格式输出分析结果：

## 手相健康评分：XX/100

评分依据（中医手诊角度）：
+ 手掌色泽状态 XX +XX分
+ 手指形态健康度 XX +XX分
+ 主要掌纹清晰度 XX +XX分
+ 手掌肌肉丰满度 XX +XX分
- 异常色泽/形态 XX -XX分

评分说明：基于中医手诊理论分析，反映身体健康状况趋势。

---

## 一句话总结
用一句话（30字以内）概括手相健康分析的主要结论。

---

## 一、手掌形态分析
- 形态特征：
- 色泽分析：
- 脏腑关联（脾胃、气血）：
- 健康提示：
- 调理建议：

---

## 二、手指与指甲分析
- 手指形态：
- 指甲色泽/月牙：
- 五脏对应状态（肝心脾肺肾）：
- 健康提示：
- 调理建议：

---

## 三、主要掌纹健康分析
- 生命线（肾精、体质）：
- 智慧线（心脑血管）：
- 感情线（心脏、情绪）：
- 健康线（免疫力）：
- 综合健康提示：

---

## 四、中医调理建议
- 饮食调养（药食同源）：
- 经络穴位按摩：
- 起居作息建议：
- 情志调节：

---

## 温馨提示
- 中医手诊属于传统医学范畴，分析结果仅供参考健康管理
- 如有明显不适，建议及时就医检查
- 治未病，重预防，保持健康生活方式

## 参考来源
【中医经典参考】
列出本报告中参考的中医经典或理论（2-3条），格式：
- 《黄帝内经》：相关理论简述
- 《望诊遵经》：相关理论简述
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
     * 检查远程图片URL是否可访问
     * 使用 GET 请求 + Range 头，只下载前1KB验证可访问性
     */
    protected function isImageUrlAccessible(string $url): bool
    {
        try {
            // 使用 GET 请求 + Range 头，只下载前1KB，避免下载整个大文件
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->withHeaders(['Range' => 'bytes=0-1023'])
                ->get($url);
            
            // 200 或 206 (Partial Content) 都表示可访问
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Image URL accessibility check failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 将本地URL转换为生产URL
     */
    protected function convertLocalUrlToProduction(string $url): string
    {
        $productionUrl = SystemConfigService::get('app_url_production');
        if (empty($productionUrl)) {
            return $url;
        }
        
        $parsedUrl = parse_url($url);
        $productionParsed = parse_url($productionUrl);
        
        if ($parsedUrl && $productionParsed) {
            // 替换 host 和 port
            $parsedUrl['host'] = $productionParsed['host'] ?? 'localhost';
            $parsedUrl['port'] = $productionParsed['port'] ?? null;
            $parsedUrl['scheme'] = $productionParsed['scheme'] ?? 'http';
            
            // 重建 URL
            $scheme   = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
            $host     = $parsedUrl['host'] ?? '';
            $port     = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
            $path     = $parsedUrl['path'] ?? '';
            $query    = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
            $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';
            
            return $scheme . $host . $port . $path . $query . $fragment;
        }
        
        return $url;
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

            // 尝试多个可能的路径
            $possiblePaths = [
                public_path('storage/' . $relativePath),           // public/storage/ (symlink)
                storage_path('app/public/' . $relativePath),       // storage/app/public/ (actual storage)
            ];

            $filePath = null;
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath)) {
                    $filePath = $testPath;
                    break;
                }
            }

            if (!$filePath) {
                Log::warning('Local image file not found', [
                    'paths' => $possiblePaths,
                    'url' => $url,
                ]);
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

    /**
     * 手相分析（基于图片）
     *
     * @param array $imageUrls 手掌图片URL列表
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzePalm(array $imageUrls, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting palm analysis', ['image_urls' => $imageUrls]);

        $prompt = $this->getPalmAnalysisPrompt($gender, $age);

        try {
            $result = $this->callVisionApi($imageUrls, $prompt, 'palm');

            Log::info('Palm analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Palm analysis failed', [
                'error' => $e->getMessage(),
                'image_urls' => $imageUrls,
            ]);
            throw $e;
        }
    }

    /**
     * 手相分析（基于文字描述，无图片）
     *
     * @param string $text 用户文字描述
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzePalmByText(string $text, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting palm analysis by text', ['text_length' => strlen($text)]);

        try {
            $result = $this->callTextApi($text, 'palm', $gender, $age);

            Log::info('Palm text analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Palm text analysis failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 眼部分析（基于图片）
     *
     * @param array $imageUrls 眼部图片URL列表
     * @param int $gender 性别:1男 2女
     * @param int $age 年龄
     * @return array
     * @throws \Exception
     */
    public function analyzeEye(array $imageUrls, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting eye analysis', ['image_urls' => $imageUrls]);

        $prompt = $this->getEyeAnalysisPrompt($gender, $age);

        try {
            $result = $this->callVisionApi($imageUrls, $prompt, 'eye');

            Log::info('Eye analysis completed', [
                'result_length' => strlen($result['content'] ?? ''),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Eye analysis failed', [
                'error' => $e->getMessage(),
                'image_urls' => $imageUrls,
            ]);
            throw $e;
        }
    }

    /**
     * 眼部分析（基于文字描述）
     *
     * @param string $text 用户描述的眼部症状
     * @param int $gender 性别 1男 2女
     * @param int $age 年龄
     * @return array
     */
    public function analyzeEyeByText(string $text, int $gender = 0, int $age = 0): array
    {
        Log::info('Starting eye analysis by text', ['text_length' => strlen($text)]);
        try {
            $result = $this->callTextApi($text, 'eye', $gender, $age);
            Log::info('Eye text analysis completed', [
                'content_length' => strlen($result['content'] ?? ''),
            ]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Eye text analysis failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 获取眼部分析系统提示词（文字描述模式）
     *
     * @param int $gender 性别 1男 2女
     * @param int $age 年龄
     * @return string
     */
    protected function getEyeTextSystemPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医目诊专家，擅长根据用户描述的眼部特征进行中医眼部分析。

【重要原则】
1. 目诊属于中医传统诊断方法，分析结果仅供参考健康调理参考
2. 使用"倾向""可能""传统解读"等描述，避免确定性断言
3. 强调"肝开窍于目"的中医理论，从眼部反映肝胆、气血状态
4. 避免负面、宿命论的解读，保持正面引导
5. 如有明显眼部病变症状，建议及时就医诊治

{$this->userProfileContext($gender, $age)}

【中医目诊理论基础】
中医认为"目者，肝之官也"，眼睛与五脏六腑密切相关：
- 眼白（白睛）反映肺与大肠状态
- 眼黑（黑睛）反映肝与胆状态
- 瞳孔反映肾与膀胱状态
- 眼睑反映脾与胃状态
- 两眦血络反映心与小肠状态

请从以下维度进行分析：

一、眼白分析
- 眼白颜色（是否红、黄、苍白）
- 血丝分布情况
- 传统解读中的肺气状态

二、眼部形态分析
- 眼袋与水肿情况
- 黑眼圈程度
- 眼皮浮肿与否
- 反映的脾肾状态

三、眼神与光泽
- 眼睛是否有神
- 湿润度与干涩情况
- 反映的气血津液状态

四、相关脏腑辨证
- 肝胆功能状态（肝开窍于目）
- 气血盛衰判断
- 肾精充足与否

请严格按照以下格式输出分析结果：

## 眼部综合评分：XX/100

评分依据（中医目诊角度）：
+ 眼神明亮度 XX +XX分
+ 眼白清亮度 XX +XX分
+ 眼部形态 XX +XX分
- 异常表现 XX -XX分

评分说明：基于传统中医目诊理论分析，仅供参考。

---

## 一句话总结
用一句话（30字以内）概括眼部分析的主要结论。

---

## 一、眼白（白睛）分析
- 形态特征：
- 血丝情况：
- 传统解读（肺与大肠）：
- 相关建议：

---

## 二、眼部形态分析
- 眼袋情况：
- 黑眼圈程度：
- 眼皮状态：
- 传统解读（脾肾状态）：
- 调理建议：

---

## 三、眼神与光泽分析
- 眼神状态：
- 湿润程度：
- 传统解读（气血津液）：
- 相关建议：

---

## 四、脏腑辨证分析
- 肝胆状态（肝开窍于目）：
- 气血盛衰：
- 肾精状态：
- 整体体质倾向：

---

## 综合调养建议
- 饮食调理（养肝明目食物）：
- 起居调摄（用眼卫生）：
- 情志调节（避免肝火上炎）：
- 穴位保健（眼周穴位按摩）：
- 中药调理参考（需在医师指导下使用）：

---

## 温馨提示
- 目诊属于中医传统诊断方法，不具备现代医学验证依据
- 如有明显眼部不适、视力下降、眼红眼痛等症状，请及时就医
- 建议以保健调理心态参考，不宜过分依赖

## 参考来源
【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述
PROMPT;
    }

    /**
     * 获取手相分析提示词（基于图片）
     *
     * @param int $gender 性别 1男 2女
     * @param int $age 年龄
     * @return string
     */
    protected function getPalmAnalysisPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医手诊专家，请根据提供的手掌图片进行中医手相健康分析。

【重要原则】
1. 基于中医手诊理论，通过手掌形态、色泽、纹理等特征分析健康状况
2. 中医手诊属于传统医学范畴，分析结果仅供参考健康管理
3. 使用"倾向""可能""提示"等描述，避免确定性诊断
4. 强调"治未病"理念，注重健康预防和调理
5. 如有明显健康问题，建议用户及时就医检查

{$this->userProfileContext($gender, $age)}

请从以下中医手诊角度进行分析：

一、手掌形态分析
- 手掌厚薄、大小、肌肉丰满度
- 手掌色泽（红润、苍白、潮红、暗紫等）
- 对应脏腑：脾胃、气血状态

二、手指形态分析
- 手指粗细、长短比例
- 指甲色泽、月牙、纹路
- 五指对应五脏（拇指-脾、食指-肝、中指-心、无名指-肺、小指-肾）

三、主要掌纹健康分析
- 生命线：生命力、先天体质、肾精状态
- 智慧线：心脑血管、神经系统
- 感情线：心脏功能、情绪状态
- 健康线：免疫系统与整体健康

请严格按照以下格式输出分析结果：

## 手相健康评分：XX/100

评分依据（中医手诊角度）：
+ 手掌色泽状态 XX +XX分
+ 手指形态健康度 XX +XX分
+ 主要掌纹清晰度 XX +XX分
+ 手掌肌肉丰满度 XX +XX分
- 异常色泽/形态 XX -XX分

评分说明：基于中医手诊理论分析，反映身体健康状况趋势。

---

## 一句话总结
用一句话（30字以内）概括手相健康分析的主要结论。

---

## 一、手掌形态分析
- 形态特征：
- 色泽分析：
- 脏腑关联（脾胃、气血）：
- 健康提示：
- 调理建议：

---

## 二、手指与指甲分析
- 手指形态：
- 指甲色泽/月牙：
- 五脏对应状态（肝心脾肺肾）：
- 健康提示：
- 调理建议：

---

## 三、主要掌纹健康分析
- 生命线（肾精、体质）：
- 智慧线（心脑血管）：
- 感情线（心脏、情绪）：
- 健康线（免疫力）：
- 综合健康提示：

---

## 四、中医调理建议
- 饮食调养（药食同源）：
- 经络穴位按摩：
- 起居作息建议：
- 情志调节：

---

## 温馨提示
- 中医手诊属于传统医学范畴，分析结果仅供参考健康管理
- 如有明显不适，建议及时就医检查
- 治未病，重预防，保持健康生活方式

## 参考来源
【中医经典参考】
列出本报告中参考的中医经典或理论（2-3条），格式：
- 《黄帝内经》：相关理论简述
- 《望诊遵经》：相关理论简述
PROMPT;
    }

    /**
     * 获取眼部分析提示词（基于图片）
     *
     * @param int $gender 性别 1男 2女
     * @param int $age 年龄
     * @return string
     */
    protected function getEyeAnalysisPrompt(int $gender = 0, int $age = 0): string
    {
        return <<<PROMPT
你是一位资深的中医目诊专家，请根据提供的眼部图片进行中医眼部分析。

【重要原则】
1. 目诊属于中医传统诊断方法，分析结果仅供参考健康调理参考
2. 使用"倾向""可能""传统解读"等描述，避免确定性断言
3. 强调"肝开窍于目"的中医理论，从眼部反映肝胆、气血状态
4. 避免负面、宿命论的解读，保持正面引导
5. 如有明显眼部病变症状，建议及时就医诊治

{$this->userProfileContext($gender, $age)}

【中医目诊理论基础】
中医认为"目者，肝之官也"，眼睛与五脏六腑密切相关：
- 眼白（白睛）反映肺与大肠状态
- 眼黑（黑睛）反映肝与胆状态
- 瞳孔反映肾与膀胱状态
- 眼睑反映脾与胃状态
- 两眦血络反映心与小肠状态

请从以下维度进行分析：

一、眼白分析
- 眼白颜色（是否红、黄、苍白）
- 血丝分布情况
- 传统解读中的肺气状态

二、眼部形态分析
- 眼袋与水肿情况
- 黑眼圈程度
- 眼皮浮肿与否
- 反映的脾肾状态

三、眼神与光泽
- 眼睛是否有神
- 湿润度与干涩情况
- 反映的气血津液状态

请严格按照以下格式输出分析结果：

## 眼部综合评分：XX/100

评分依据（中医目诊角度）：
+ 眼神明亮度 XX +XX分
+ 眼白清亮度 XX +XX分
+ 眼部形态 XX +XX分
- 异常表现 XX -XX分

评分说明：基于传统中医目诊理论分析，仅供参考。

---

## 一、眼白（白睛）分析
- 形态特征：
- 血丝情况：
- 传统解读（肺与大肠）：
- 相关建议：

---

## 二、眼部形态分析
- 眼袋情况：
- 黑眼圈程度：
- 眼皮状态：
- 传统解读（脾肾状态）：
- 调理建议：

---

## 三、眼神与光泽分析
- 眼神状态：
- 湿润程度：
- 传统解读（气血津液）：
- 相关建议：

---

## 四、脏腑辨证分析
- 肝胆状态（肝开窍于目）：
- 气血盛衰：
- 肾精状态：
- 整体体质倾向：

---

## 综合调养建议
- 饮食调理（养肝明目食物）：
- 起居调摄（用眼卫生）：
- 情志调节（避免肝火上炎）：
- 穴位保健（眼周穴位按摩）：

---

## 温馨提示
- 目诊属于中医传统诊断方法，不具备现代医学验证依据
- 如有明显眼部不适、视力下降、眼红眼痛等症状，请及时就医
- 建议以保健调理心态参考，不宜过分依赖

## 参考来源
【中医经典参考】
列出本报告中参考的中医古籍或经典理论（2-3条），格式：
- 《书名》：相关理论简述
PROMPT;
    }
}
