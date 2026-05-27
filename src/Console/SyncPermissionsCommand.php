<?php

namespace MdtStar\Nexus\Console;

use MdtStar\Nexus\Services\PermissionSyncer;
use Illuminate\Console\Command;

/**
 * 同步模块权限指令
 *
 * 扫描已安装模块的 composer.json，同步权限和菜单到数据库。
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'platform:sync-permissions
                            {--package= : 指定要同步的包名，不指定则同步所有已注册包}';

    protected $description = '同步模块权限和菜单到数据库';

    protected PermissionSyncer $syncer;

    public function __construct(PermissionSyncer $syncer)
    {
        parent::__construct();

        $this->syncer = $syncer;
    }

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $packageName = $this->option('package');

        if ($packageName) {
            $this->info("正在同步模块: {$packageName}");

            // 查找模块的 composer.json 并解析
            $config = $this->resolveModuleConfig($packageName);

            if (! $config) {
                $this->error("未找到模块配置: {$packageName}");

                return self::FAILURE;
            }

            $this->syncer->sync($packageName, $config);
            $this->info("模块 {$packageName} 同步完成");

            return self::SUCCESS;
        }

        // 同步所有已注册包
        $this->info('正在同步所有已注册模块...');

        // TODO: 扫描所有已安装模块并同步
        $this->warn('批量同步功能尚未实现，请使用 --package 指定单个模块');

        return self::SUCCESS;
    }

    /**
     * 解析模块配置
     *
     * @param string $packageName
     * @return array|null
     */
    protected function resolveModuleConfig(string $packageName): ?array
    {
        // 通过 Composer 安装路径查找模块的 composer.json
        $basePath = base_path('vendor/' . $packageName);
        $composerPath = $basePath . '/composer.json';

        if (! file_exists($composerPath)) {
            return null;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        return $composer['extra']['nexus'] ?? null;
    }
}
