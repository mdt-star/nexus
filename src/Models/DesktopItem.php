<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 桌面项模型
 *
 * 用户桌面上的菜单项，由前端直接创建/更新。
 * 不再关联 menus 表，所有字段直接存储。
 *
 * @property int $id
 * @property int $desktop_id
 * @property string $label
 * @property string $path
 * @property string|null $component
 * @property string|null $icon
 * @property array|null $custom
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DesktopItem extends Model
{
    use Filterable;

    protected $table = 'desktop_items';

    protected $fillable = [
        'desktop_id',
        'label',
        'path',
        'component',
        'icon',
        'custom',
        'sort',
    ];

    protected $casts = [
        'desktop_id' => 'integer',
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
}
