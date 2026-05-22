<?php

namespace MdtStar\Nexus\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * 包管理模型
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Package extends Model
{
    protected $table = 'packages';

    protected $fillable = [
        'name',
    ];

    /**
     * 包关联的所有权限
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'package_id');
    }

    /**
     * 通过包名获取 ID（走全表缓存）
     *
     * 全表一次缓存，按 name 索引，O(1) 查找。
     *
     * @param string $name Composer 包名
     * @return int|null
     */
    public static function idByName(string $name): ?int
    {
        return static::allCached()->get($name)?->id;
    }

    /**
     * 获取全表缓存（按 name 索引）
     *
     * @return Collection<string, static>
     */
    public static function allCached(): Collection
    {
        return Cache::rememberForever('nexus_packages_all', function () {
            return static::all()->keyBy('name');
        });
    }

    /**
     * 清理全表缓存
     *
     * 在包安装、卸载、更新时调用，确保下次查询拿到最新数据。
     */
    public static function flushCache(): void
    {
        Cache::forget('nexus_packages_all');
    }
}
