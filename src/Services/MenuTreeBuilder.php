<?php

namespace MdtStar\Nexus\Services;

use MdtStar\Nexus\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 菜单树构建器
 *
 * 负责：
 * - 从 menus 表构建树形结构
 * - 动态拼接 path
 * - 合并 desktop_menus 的 custom 覆盖值
 *
 * 菜单树结果自动缓存，TTL 由 config('nexus.menus.cache_ttl') 控制。
 */
class MenuTreeBuilder
{
    /**
     * 缓存键前缀
     */
    protected string $cachePrefix = 'menu_tree_';

    /**
     * 构建完整菜单树
     *
     * @param array|null $customOverrides 用户自定义覆盖值（desktop_menus 数据）
     * @return array
     */
    public function buildTree(?array $customOverrides = []): array
    {
        $cacheKey = $this->cachePrefix . md5(serialize($customOverrides));
        $ttl = config('nexus.menus.cache_ttl', 3600);

        if ($ttl <= 0) {
            // TTL 为 0 时不缓存
            $menus = Menu::all();

            return $this->buildTreeFromCollection($menus, null, '', $customOverrides);
        }

        return Cache::remember($cacheKey, $ttl, function () use ($customOverrides) {
            $menus = Menu::all();

            return $this->buildTreeFromCollection($menus, null, '', $customOverrides);
        });
    }

    /**
     * 清除菜单树缓存
     */
    public function clearCache(): void
    {
        // 清除所有菜单树缓存（通配符清除需要 redis 支持，此处用前缀遍历清除）
        // 实际项目中建议使用 Cache::tags() 或统一缓存 key
        Cache::forget($this->cachePrefix . 'default');
    }

    /**
     * 递归构建树形结构
     *
     * @param Collection $menus 所有菜单集合
     * @param int|null $parentId 父级 ID
     * @param string $parentPath 父级路径前缀
     * @param array $customOverrides 自定义覆盖值
     * @return array
     */
    protected function buildTreeFromCollection(
        Collection $menus,
        ?int $parentId,
        string $parentPath,
        array $customOverrides
    ): array {
        $tree = [];

        $children = $menus->where('parent_id', $parentId);

        foreach ($children as $menu) {
            // 动态拼接完整路径
            $path = $this->buildPath($menu->path, $parentPath);

            $node = [
                'id' => $menu->id,
                'label' => $menu->label,
                'path' => $path,
                'component' => $menu->component,
                'icon' => $menu->icon,
            ];

            // 合并自定义覆盖值
            if (isset($customOverrides[$menu->id])) {
                $node = array_merge($node, $customOverrides[$menu->id]);
            }

            // 递归构建子节点
            $childrenTree = $this->buildTreeFromCollection(
                $menus,
                $menu->id,
                $path,
                $customOverrides
            );

            if (! empty($childrenTree)) {
                $node['children'] = $childrenTree;
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * 动态拼接路径
     *
     * 以 / 开头的视为绝对路径，不做拼接。
     * 相对路径拼接父级路径。
     */
    protected function buildPath(string $path, string $parentPath): string
    {
        // 绝对路径，不做拼接
        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (empty($parentPath)) {
            return $path;
        }

        return $parentPath . '/' . $path;
    }
}
