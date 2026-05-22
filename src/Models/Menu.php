<?php

namespace MdtStar\Nexus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 菜单发布池模型
 *
 * 通过 parent_id 与 permissions 表保持一致的树形层级关系。
 * 落库时 path 保持原始值，数据返回时动态拼接完整路径。
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $label
 * @property string $path
 * @property string|null $component
 * @property string|null $icon
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Menu extends Model
{
    protected $table = 'menus';

    protected $fillable = [
        'parent_id',
        'label',
        'path',
        'component',
        'icon',
    ];

    protected $casts = [
        'parent_id' => 'integer',
    ];

    /**
     * 父级菜单
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 子级菜单
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
