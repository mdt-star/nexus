<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 用户桌面配置模型
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $region
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Desktop extends Model
{
    use Filterable;

    protected $table = 'desktops';

    protected $fillable = [
        'user_id',
        'name',
        'region',
        'is_default',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * 桌面关联的项
     */
    public function items(): HasMany
    {
        return $this->hasMany(DesktopItem::class, 'desktop_id');
    }
}
