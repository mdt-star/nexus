<?php

namespace MdtStar\Nexus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 用户桌面配置模型
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $region
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Desktop extends Model
{
    protected $table = 'desktops';

    protected $fillable = [
        'user_id',
        'name',
        'region',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    /**
     * 桌面关联的菜单
     */
    public function desktopMenus(): HasMany
    {
        return $this->hasMany(DesktopMenu::class, 'desktop_id');
    }
}
