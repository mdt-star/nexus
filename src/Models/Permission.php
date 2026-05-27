<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 功能权限节点模型
 *
 * 权限节点字典表，仅存储层级结构。
 * name 通过多国语言包匹配 tag 获取，不存储在数据库。
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int|null $package_id
 * @property string $tag
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Permission extends Model
{
    use Filterable;

    protected $table = 'permissions';

    protected $fillable = [
        'parent_id',
        'package_id',
        'tag',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'package_id' => 'integer',
    ];

    /**
     * 父级权限
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 子级权限
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * 所属包
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
