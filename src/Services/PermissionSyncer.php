<?php

namespace MdtStar\Nexus\Services;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * 权限同步器
 *
 * 负责：
 * - 解析模块 composer.json 的 permissions 配置
 * - 落库 permissions 表
 * - 保持树形结构
 * - tag 原样保存，不拼接父级前缀
 * - 唯一性由 (package_id, tag, parent_id) 联合唯一约束保证
 */
class PermissionSyncer
{
    /**
     * 同步模块权限
     *
     * @param string $packageName Composer 包名
     * @param array $config 模块配置（extra.nexus）
     * @return void
     */
    public function sync(string $packageName, array $config): void
    {
        DB::transaction(function () use ($packageName, $config) {
            $package = Package::firstOrCreate(['name' => $packageName]);

            $permissions = $config['nexus']['permissions'] ?? [];

            foreach ($permissions as $permConfig) {
                $this->syncPermissionTree($package, $permConfig, null);
            }

            // 同步完成后清理全表缓存
            Package::flushCache();
        });
    }

    /**
     * 递归同步权限树
     *
     * tag 原样保存，不拼接父级前缀。
     * 树形结构完全靠 parent_id 维护。
     * 唯一性由 (package_id, tag, parent_id) 联合唯一约束保证。
     *
     * children 支持两种格式：
     * 1. 对象数组：`[{ "tag": "list" }, { "tag": "add" }]`
     * 2. 字符串数组：`["list", "add", "detail"]`
     *
     * @param Package $package 包模型
     * @param array $config 权限配置
     * @param int|null $parentPermissionId 父级权限 id
     */
    protected function syncPermissionTree(Package $package, array $config, ?int $parentPermissionId): void
    {
        $tag = $config['tag'];

        // tag 原样保存，不拼接父级前缀
        $permission = Permission::updateOrCreate(
            [
                'package_id' => $package->id,
                'tag' => $tag,
                'parent_id' => $parentPermissionId,
            ],
            []
        );

        // 递归处理子节点
        if (isset($config['children'])) {
            foreach ($config['children'] as $childConfig) {
                // 兼容字符串数组简写格式：["list", "add"]
                if (is_string($childConfig)) {
                    $childConfig = ['tag' => $childConfig];
                }
                $this->syncPermissionTree($package, $childConfig, $permission->id);
            }
        }
    }

    /**
     * 卸载模块时清理关联数据
     *
     * @param string $packageName Composer 包名
     * @return void
     */
    public function uninstall(string $packageName): void
    {
        DB::transaction(function () use ($packageName) {
            $package = Package::where('name', $packageName)->first();

            if (! $package) {
                return;
            }

            // 直接通过 package_id 删除所有关联权限
            Permission::where('package_id', $package->id)->delete();

            // 删除包记录
            $package->delete();

            // 清理全表缓存
            Package::flushCache();
        });
    }
}
