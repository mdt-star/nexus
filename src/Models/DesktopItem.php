<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 桌面项模型
 *
 * 用户桌面上的菜单项，由前端直接创建/更新。
 * 支持树状结构，通过 parent_id 实现父子层级。
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $desktop_id
 * @property string $label
 * @property string $path
 * @property string|null $component
 * @property string|null $icon
 * @property array|null $custom
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DesktopItem> $children
 * @property-read DesktopItem|null $parent
 */
class DesktopItem extends Model
{
    use Filterable;

    protected $table = 'desktop_items';

    protected $fillable = [
        'desktop_id',
        'parent_id',
        'label',
        'path',
        'component',
        'icon',
        'custom',
        'sort',
    ];

    protected $casts = [
        'desktop_id' => 'integer',
        'parent_id' => 'integer',
        'sort' => 'integer',
        'custom' => 'array',
    ];

    /**
     * 关联的桌面
     */
    public function desktop(): BelongsTo
    {
        return $this->belongsTo(Desktop::class, 'desktop_id');
    }

    /**
     * 父级桌面项
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 子级桌面项
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }
}
