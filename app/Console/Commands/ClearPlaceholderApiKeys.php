<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:clear-placeholder-keys')]
#[Description('清理 ai_models 表中的占位符 API Key，确保生产环境使用真实配置')]
class ClearPlaceholderApiKeys extends Command
{
    /**
     * 占位符特征值
     */
    protected array $placeholders = [
        'test-key',
        'placeholder',
        'your-api-key',
        'sk-placeholder',
        'sk-test',
        'change-me',
        'xxxxx',
        'mock',
        'example',
    ];

    public function handle(): int
    {
        $models = AiModel::all();
        $cleared = 0;

        foreach ($models as $model) {
            $key = trim((string) $model->api_key);
            if ($key === '') {
                continue;
            }

            $lower = strtolower($key);
            $isPlaceholder = false;

            foreach ($this->placeholders as $p) {
                if (str_contains($lower, $p)) {
                    $isPlaceholder = true;
                    break;
                }
            }

            if (!$isPlaceholder && preg_match('/^(test|placeholder|demo|example|change|xxxx|your)/i', $lower)) {
                $isPlaceholder = true;
            }

            if ($isPlaceholder) {
                $this->warn("清空模型 [{$model->id}] {$model->name} 的占位符 api_key（{$model->api_key}）");
                $model->api_key = '';
                $model->is_enabled = 0;
                $model->save();
                $cleared++;
            }
        }

        if ($cleared > 0) {
            $this->info("完成：共清理 {$cleared} 条占位符 API Key，对应模型已置为禁用状态");
            $this->line('请在管理后台 [AI 管理] 中配置真实 API Key 后再启用。');
        } else {
            $this->info('未发现占位符 API Key，无需清理');
        }

        return self::SUCCESS;
    }
}
