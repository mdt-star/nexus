<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\Permissionable;
use MdtStar\Nexus\Http\Requests\StorePermissionableRequest;
use MdtStar\Nexus\Http\Requests\PermissionableFilterRequest;
use Illuminate\Routing\Controller;

/**
 * 已授权权限管理接口
 *
 * 管理用户/角色被授予的功能权限 tag。
 * model 参数使用 "完整类名@id" 格式，如 "App\Models\User@1"。
 */
class PermissionableController extends Controller
{
    public function index(PermissionableFilterRequest $request)
    {
        return Permissionable::filter($request->filters())->get();
    }

    public function store(StorePermissionableRequest $request)
    {
        $data = $request->validated();

        // 解析 "App\Models\User@1" → model_type + model_id
        $parts = explode('@', $data['model']);
        $data['model_type'] = $parts[0];
        $data['model_id'] = (int) $parts[1];
        unset($data['model']);

        return Permissionable::create($data);
    }

    public function destroy(Permissionable $permissionable)
    {
        $permissionable->delete();
        return response()->noContent();
    }
}
