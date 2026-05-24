<?php

namespace MdtStar\Nexus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 桌面菜单关联模型
 *
 * 用户可将菜单从 menus 拖拽到桌面，并支持自定义覆盖原菜单项的值。
 * 模型层自动合并 custom 与 menus 原值，优先使用 custom。
 *
 * @property int $id
 * @property int $desktop_id
 * @property int $menu_id
 * @property array|null $custom
 * @property int $sort
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DesktopMenu extends Model
{
    protected $table = 'desktop_menus';

    protected $fillable = [
        'desktop_id',
        'menu_id',
        'custom',
        'sort',
    ];

    protected $casts = [
        'desktop_id' => 'integer',
        'menu_id' => 'integer',
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
     * 关联的菜单
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * 获取合并后的菜单数据（custom 覆盖原值）
     */
    public function getMergedMenuAttribute(): array
    {
        $menu = $this->menu;

        if (! $menu) {
            return [];
        }

        $base = [
            'label' => $menu->label,
            'path' => $menu->path,
            'component' => $menu->component,
            'icon' => $menu->icon,
        ];

        if ($this->custom) {
            return array_merge($base, $this->custom);
        }

        return $base;
    }
}
