<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 模型访问权限模型
 *
 * 控制主体（用户/角色/团队等）对特定模型的读写删权限及数据范围。
 * 与 Permission（功能标记）不同，本模型控制的是数据层面的访问权限。
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $class
 * @property bool $can_read
 * @property bool $can_write
 * @property bool $can_delete
 * @property string|null $scope_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Model|\MdtStar\Nexus\Contracts\HasModelAccess $subject
 */
class ModelAccess extends Model
{
    protected $table = 'model_accesses';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'class',
        'can_read',
        'can_write',
        'can_delete',
        'scope_key',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'can_read' => 'boolean',
        'can_write' => 'boolean',
        'can_delete' => 'boolean',
    ];

    /**
     * 多态关联主体
     *
     * subject_type 存储主体模型全限定类名（如 App\Models\User），
     * 与主体模型的 getMorphClass() 返回值一致。
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
