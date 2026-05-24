<?php

namespace MdtStar\Nexus\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * 功能权限主体接口
 *
 * 实现此接口的模型（User、Role 等）可通过多态关联获取已授权的 tag 列表。
 * 中间件通过此接口统一校验权限，不直接操作 Permissionable 表。
 */
interface HasPermission
{
    /**
     * 多态关联：已授权的权限 tag
     *
     * @return MorphMany
     */
    public function permissionTags(): MorphMany;

    /**
     * 获取当前主体已授权的 tag 列表
     *
     * 返回 Collection of stdClass { tag, package_id }
     * 建议走缓存，通过 permissionCacheKey() 控制缓存键。
     *
     * @return Collection
     */
    public function getPermissionTags(): Collection;

    /**
     * 判断当前主体是否拥有指定 tag
     *
     * @param string $tag 权限标识
     * @param int|null $packageId 指定包 ID，null 表示全局权限
     * @return bool
     */
    public function hasTag(string $tag, ?int $packageId = null): bool;

    /**
     * 权限缓存键
     *
     * @return string
     */
    public function permissionCacheKey(): string;

    /**
     * 清理权限缓存
     *
     * @return void
     */
    public function flushPermissionCache(): void;
}
