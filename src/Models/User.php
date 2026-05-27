<?php

namespace MdtStar\Nexus\Models;

use MdtStar\Nexus\Contracts\HasModelAccess;
use MdtStar\Nexus\Contracts\HasPermission;
use MdtStar\Nexus\Traits\Filterable;
use MdtStar\Nexus\Traits\HasModelAccessTrait;
use MdtStar\Nexus\Traits\HasPermissionTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 用户模型
 *
 * 继承 Laravel 基础 Authenticatable，作为 model_accesses 的多态作用主体。
 * 本包通过 NexusServiceProvider 将此模型注入为 Laravel 默认认证用户模型，
 * 因此 Auth::user() 返回的即为此 User 实例。
 *
 * getModelAccess() 自动穿透到用户所属角色组：
 * - 用户自身权限
 * - 用户所属角色的权限（用户权限覆盖角色权限）
 *
 * getPermissionTags() 自动穿透到用户所属角色组：
 * - 用户自身的 tag
 * - 用户所属角色的 tag（合并去重）
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\Role> $roles
 * @property-read \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\ModelAccess> $modelAccesses
 */
class User extends Authenticatable implements HasModelAccess, HasPermission
{
    use Filterable;
    use HasModelAccessTrait;
    use HasPermissionTrait {
        getPermissionTags as private getOwnPermissionTags;
    }

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 判断当前用户是否为超级管理员
     *
     * 超级管理员拥有至高无上的权限，可以跳过所有权限检查。
     * 默认为系统中第一个用户（id = 1），可通过配置 'nexus.super_admin_id' 自定义。
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        $superAdminId = config('nexus.super_admin_id', 1);

        return $this->id === (int) $superAdminId;
    }

    /**
     * 用户所属角色组
     *
     * 按 pivot_sort 升序排列，sort 越小优先级越高。
     * 第一个角色为主角色，其权限优先级最高。
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id')
            ->withPivot('sort')
            ->orderBy('pivot_sort');
    }

    /**
     * 获取用户对指定模型的访问权限集合
     *
     * 穿透逻辑（严格优先级）：
     * 1. 用户自身权限（最高优先级）
     * 2. 角色组权限（按 sort 排序，第一个角色优先级最高，依次递减）
     * 3. 高优先级覆盖低优先级（相同 class 时以高优先级为准）
     *
     * @param string|null $modelClass 目标模型全限定类名，null 表示所有
     * @return \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\ModelAccess>
     */
    public function getModelAccess(?string $modelClass = null): Collection
    {
        // 1. 用户自身权限
        $userAccess = $this->modelAccesses()
            ->when($modelClass, fn($q) => $q->where('class', $modelClass))
            ->get();

        // 2. 穿透角色组权限（按 sort 升序，第一个角色优先级最高）
        //    高优先级角色覆盖低优先级角色的相同 class 记录
        $roleAccess = collect();
        foreach ($this->roles as $role) {
            $currentRoleAccess = $role->getModelAccess($modelClass);

            // 当前角色中，与已有记录相同 class 的剔除（已有记录保留，不覆盖）
            $newAccess = $currentRoleAccess->reject(function ($current) use ($roleAccess) {
                return $roleAccess->contains(function ($existing) use ($current) {
                    return $existing->class === $current->class;
                });
            });

            $roleAccess = $roleAccess->merge($newAccess);
        }

        // 3. 合并：用户权限覆盖角色权限（相同 class 时以用户为准）
        return $roleAccess->reject(function ($roleAcc) use ($userAccess) {
            return $userAccess->contains(function ($userAcc) use ($roleAcc) {
                return $userAcc->class === $roleAcc->class;
            });
        })->concat($userAccess);
    }

    /**
     * 获取用户已授权的 tag 列表
     *
     * 穿透逻辑：
     * 1. 用户自身的 tag（调 trait 默认实现，走多态）
     * 2. 用户所属角色的 tag（调 Role 的接口方法）
     * 3. 合并去重（相同 tag + package_id 只保留一份）
     *
     * @return Collection
     */
    public function getPermissionTags(): Collection
    {
        return Cache::remember($this->permissionCacheKey(), 3600, function () {
            // 1. 用户自身的 tag
            $own = $this->getOwnPermissionTags();

            // 2. 角色组的 tag
            $roleTags = $this->roles->flatMap(fn(Role $role) => $role->getPermissionTags());

            // 3. 合并去重
            return $own->concat($roleTags)->unique(fn($item) => $item->tag . '@' . $item->package_id);
        });
    }
}
