<?php

namespace MdtStar\Nexus\Observers;

use MdtStar\Nexus\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * 功能权限标记变更监听
 *
 * 监听 Permission（功能标记）模型的变更事件，清理相关缓存。
 * 与 ModelAccess（模型访问权限）无关，本 Observer 只处理功能标记缓存。
 * 兼容所有缓存驱动（file/database/redis/memcached），
 * 不使用 Cache::tags() 避免驱动限制。
 */
class PermissionObserver
{
    /**
     * 缓存键前缀
     */
    protected string $cachePrefix = 'permission_tag_';

    /**
     * 监听创建事件
     */
    public function created(Permission $permission): void
    {
        $this->clearCache();
    }

    /**
     * 监听更新事件
     */
    public function updated(Permission $permission): void
    {
        $this->clearCache();
    }

    /**
     * 监听删除事件
     */
    public function deleted(Permission $permission): void
    {
        $this->clearCache();
    }

    /**
     * 清理功能标记相关缓存
     *
     * 使用 Cache::forget() 按 key 清除，兼容所有缓存驱动。
     * 功能标记树缓存 key 由 PermissionSyncer 或业务方定义，
     * 此处清除通用功能标记缓存前缀。
     */
    protected function clearCache(): void
    {
        // 清除功能标记树缓存（如存在）
        Cache::forget($this->cachePrefix . 'tree');
    }
}
