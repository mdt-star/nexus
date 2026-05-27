<?php

namespace MdtStar\Nexus\Services;

use MdtStar\Nexus\Models\ModelScope;
use Illuminate\Support\Facades\DB;

/**
 * 数据范围策略同步器
 *
 * 负责解析模块 composer.json 的 model_scopes 配置，
 * 同步到 model_scopes 表。
 */
class ModelScopeSyncer
{
    /**
     * 同步模块数据范围策略
     *
     * @param array $config 模块配置（extra.nexus.model_scopes）
     * @return void
     */
    public function sync(array $config): void
    {
        $scopes = $config['nexus']['model_scopes'] ?? [];

        DB::transaction(function () use ($scopes) {
            foreach ($scopes as $scopeConfig) {
                ModelScope::updateOrCreate(
                    ['key' => $scopeConfig['key']],
                    [
                        'class' => $scopeConfig['class'],
                        'model_whitelist' => $scopeConfig['model_whitelist'] ?? null,
                        'fields_whitelist' => $scopeConfig['fields_whitelist'] ?? ['*'],
                    ]
                );
            }
        });
    }

    /**
     * 卸载模块时清理关联数据
     *
     * @param string $key 策略 key
     * @return void
     */
    public function uninstall(string $key): void
    {
        ModelScope::where('key', $key)->delete();
    }
}
