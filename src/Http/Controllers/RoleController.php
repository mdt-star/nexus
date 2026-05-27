<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\Role;
use MdtStar\Nexus\Http\Requests\StoreRoleRequest;
use MdtStar\Nexus\Http\Requests\UpdateRoleRequest;
use MdtStar\Nexus\Http\Requests\RoleFilterRequest;
use Illuminate\Routing\Controller;

/**
 * 角色（用户组）管理接口
 */
class RoleController extends Controller
{
    public function index(RoleFilterRequest $request)
    {
        return Role::filter($request->filters())->get();
    }

    public function store(StoreRoleRequest $request)
    {
        return Role::create($request->validated());
    }

    public function show(Role $role)
    {
        return $role;
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role->update($request->validated());
        return $role;
    }

    public function destroy(Role $role)
    {
        $role->users()->detach();
        $role->delete();
        return response()->noContent();
    }
}
