<?php

namespace MdtStar\Nexus\Jobs;

use MdtStar\Nexus\Services\PermissionSyncer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Schema;

/**
 * 同步平台模块权限队列任务
 *
 * 当检测到 vendor 包有变更时（composer install/update），
 * 扫描所有已安装包的 composer.json，检查 extra.nexus 配置，
 * 自动同步权限到数据库。
 *
 * 安全防护：
 * - 检查核心表是否存在，规避迁移未执行的情况
 * - 通过 cache 记录已同步的包名，避免重复同步
 */
class SyncPlatformPackagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * 任务最大尝试次数
     */
    public int $tries = 3;

    /**
     * 执行任务
     */
    public function handle(PermissionSyncer $syncer): void
    {
        // 检查核心表是否存在，规避迁移未执行的情况
        if (! $this->isCoreTableExists()) {
            return;
        }

        $synced = cache()->get('platform_synced_packages', []);
        $vendorDir = base_path('vendor');

        // 扫描 vendor 中所有包的 composer.json
        $composerFiles = glob($vendorDir . '/*/*/composer.json');

        foreach ($composerFiles as $composerPath) {
            $composer = json_decode(file_get_contents($composerPath), true);

            if (! is_array($composer)) {
                continue;
            }

            $config = $composer['extra']['nexus'] ?? null;

            if ($config === null) {
                continue;
            }

            $packageName = $composer['name'] ?? '';

            if ($packageName === '' || in_array($packageName, $synced, true)) {
                continue;
            }

            $syncer->sync($packageName, $config);
            $synced[] = $packageName;
        }

        // 记录已同步的包名，避免下次重复扫描同步
        cache()->forever('platform_synced_packages', $synced);
    }

    /**
     * 检查核心表是否存在
     *
     * 如果迁移尚未执行，数据库表不存在，跳过同步避免 SQL 错误。
     */
    protected function isCoreTableExists(): bool
    {
        try {
            return Schema::hasTable('packages')
                && Schema::hasTable('permissions');
        } catch (\Throwable) {
            return false;
        }
    }
}
