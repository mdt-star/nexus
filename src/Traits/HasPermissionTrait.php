<?php

namespace MdtStar\Nexus\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MdtStar\Nexus\Models\Permissionable;

/**
 * 功能权限主体 Trait 默认实现
 *
 * 提供 HasPermission 接口的默认实现：
 * - permissionTags() 多态关联
 * - getPermissionTags() 缓存获取已授权 tag 列表
 * - hasTag() 判断是否拥有指定 tag
 * - permissionCacheKey() / flushPermissionCache() 缓存管理
 *
 * 模型可复写 permissionTags() 或 getPermissionTags() 自定义逻辑。
 */
trait HasPermissionTrait
{
    /**
     * 多态关联：已授权的权限 tag
     *
     * 模型可复写此方法添加额外条件。
     *
     * @return MorphMany
     */
    public function permissionTags(): MorphMany
    {
        return $this->morphMany(Permissionable::class, 'model');
    }

    /**
     * 获取当前主体已授权的 tag 列表
     *
     * 走缓存，通过 permissionCacheKey() 控制缓存键。
     * 模型可复写此方法实现穿透逻辑（如 User 穿透 Role）。
     *
     * @return Collection
     */
    public function getPermissionTags(): Collection
    {
        return Cache::remember($this->permissionCacheKey(), 3600, function () {
            return $this->permissionTags()->get(['tag', 'package_id']);
        });
    }

    /**
     * 判断当前主体是否拥有指定 tag
     *
     * @param string $tag 权限标识
     * @param int|null $packageId 指定包 ID，null 表示全局权限
     * @return bool
     */
    public function hasTag(string $tag, ?int $packageId = null): bool
    {
        return $this->getPermissionTags()->contains(function ($perm) use ($tag, $packageId) {
            return $perm->tag === $tag
                && ($packageId ? (int) $perm->package_id === $packageId : $perm->package_id === null);
        });
    }

    /**
     * 权限缓存键
     *
     * @return string
     */
    public function permissionCacheKey(): string
    {
        return 'permissions:' . static::class . ':' . $this->getKey();
    }

    /**
     * 清理权限缓存
     *
     * @return void
     */
    public function flushPermissionCache(): void
    {
        Cache::forget($this->permissionCacheKey());
    }
}
