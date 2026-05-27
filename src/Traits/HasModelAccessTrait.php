<?php

namespace MdtStar\Nexus\Traits;

use MdtStar\Nexus\Models\ModelAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 模型访问权限主体通用 Trait
 *
 * 为 HasModelAccess 接口提供默认实现。
 * 通过 morphMany 多态关联查询 model_accesses，
 * 并自动应用缓存。
 *
 * 使用示例：
 * ```php
 * class User extends Authenticatable implements HasModelAccess
 * {
 *     use HasModelAccessTrait;
 *
 *     // 覆盖 getModelAccess 实现权限穿透
 *     public function getModelAccess(?string $modelClass = null): Collection
 *     {
 *         // 自身权限 + 穿透角色
 *     }
 * }
 * ```
 *
 * @mixin \MdtStar\Nexus\Contracts\HasModelAccess
 */
trait HasModelAccessTrait
{
    /**
     * 模型访问权限多态关联
     *
     * 主体 → ModelAccess 的一对多多态关系，
     * subject_type 存储主体模型全限定类名，与 getMorphClass() 返回值一致。
     */
    public function modelAccesses(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ModelAccess::class, 'subject');
    }

    /**
     * 获取主体对指定模型的访问权限集合（含缓存）
     *
     * @param string|null $modelClass 目标模型全限定类名，null 表示所有
     * @return \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\ModelAccess>
     */
    public function getModelAccess(?string $modelClass = null): Collection
    {
        $ttl = config('nexus.model_access.cache_ttl', 3600);

        $query = fn () => $this->modelAccesses()
            ->when($modelClass, fn ($q) => $q->where('class', $modelClass))
            ->get();

        return $ttl > 0
            ? Cache::remember($this->getModelAccessCacheKey($modelClass), $ttl, $query)
            : $query();
    }

    /**
     * 清除主体模型访问权限缓存
     *
     * @param string|null $modelClass 指定模型类则只清除该模型的缓存
     */
    public function clearModelAccessCache(?string $modelClass = null): void
    {
        $cacheKey = $this->getModelAccessCacheKey($modelClass);
        Cache::forget($cacheKey);
    }

    /**
     * 生成模型访问权限缓存键
     *
     * @param string|null $modelClass
     * @return string
     */
    protected function getModelAccessCacheKey(?string $modelClass = null): string
    {
        $key = 'ma:' . static::class . ':' . $this->getKey();

        if ($modelClass) {
            $key .= ':' . str_replace('\\', '_', $modelClass);
        }

        return $key;
    }
}
