<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Contracts\HasModelAccess;
use MdtStar\Nexus\Contracts\HasPermission;
use MdtStar\Nexus\Traits\HasModelAccessTrait;
use MdtStar\Nexus\Traits\HasPermissionTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 用户组（角色）模型
 *
 * 作为 model_accesses 的多态主体之一，
 * 支持将模型访问权限分配给用户组，再由 User 穿透继承。
 * 同时实现 HasPermission 接口，支持功能权限 tag 的分配。
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\ModelAccess> $modelAccesses
 * @property-read \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\User> $users
 */
class Role extends Model implements HasModelAccess, HasPermission
{
    use HasModelAccessTrait;
    use HasPermissionTrait;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * 用户组下的用户
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }
}
