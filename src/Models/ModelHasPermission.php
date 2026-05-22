<?php

namespace MdtStar\Nexus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 模型已授权权限关联表
 *
 * 记录用户/角色被授予的功能权限 tag。
 * 中间件通过此表校验当前用户是否拥有指定 tag。
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $tag
 * @property int|null $package_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ModelHasPermission extends Model
{
    protected $table = 'model_has_permissions';

    protected $fillable = [
        'model_type',
        'model_id',
        'tag',
        'package_id',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
