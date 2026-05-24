<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\Permission;
use MdtStar\Nexus\Http\Requests\StorePermissionRequest;
use MdtStar\Nexus\Http\Requests\UpdatePermissionRequest;
use MdtStar\Nexus\Http\Requests\PermissionFilterRequest;
use Illuminate\Routing\Controller;

/**
 * 功能权限标记管理接口
 */
class PermissionController extends Controller
{
    public function index(PermissionFilterRequest $request)
    {
        return Permission::filter($request->filters())->get();
    }

    public function store(StorePermissionRequest $request)
    {
        return Permission::create($request->validated());
    }

    public function show(Permission $permission)
    {
        return $permission;
    }

    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $permission->update($request->validated());
        return $permission;
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return response()->noContent();
    }
}
