<?php

namespace MdtStar\Nexus\Contracts;

use Illuminate\Support\Collection;

/**
 * 模型访问权限主体契约接口
 *
 * 实现此接口的模型（User/Role/Team 等）可作为 model_accesses 的多态主体，
 * 对外提供统一的模型访问权限获取方法。
 *
 * @see \MdtStar\Nexus\Traits\HasModelAccessTrait
 */
interface HasModelAccess
{
    /**
     * 获取主体对指定模型的访问权限集合
     *
     * 主体内部自行处理权限穿透逻辑（如 User 穿透到 Role），
     * 调用方无需关心权限来源。
     *
     * @param string|null $modelClass 目标模型全限定类名，null 表示所有
     * @return \Illuminate\Support\Collection<int, \MdtStar\Nexus\Models\ModelAccess>
     */
    public function getModelAccess(?string $modelClass = null): Collection;
}
