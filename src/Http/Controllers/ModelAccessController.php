<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Http\Requests\ModelAccessFilterRequest;
use MdtStar\Nexus\Http\Requests\StoreModelAccessRequest;
use MdtStar\Nexus\Http\Requests\UpdateModelAccessRequest;
use MdtStar\Nexus\Models\ModelAccess;
use Illuminate\Routing\Controller;

/**
 * 模型访问权限接口
 *
 * 管理模型访问权限（CRUD）。
 * 控制主体（用户/角色/团队等）对特定模型的读写删权限。
 */
class ModelAccessController extends Controller
{
    /**
     * 获取模型访问权限列表
     *
     * 支持按 subject_type、subject_id、class 过滤。
     */
    public function index(ModelAccessFilterRequest $request)
    {
        return response()->json(
            ModelAccess::filter($request->filters())->paginate()
        );
    }

    /**
     * 创建模型访问权限
     */
    public function store(StoreModelAccessRequest $request)
    {
        $access = ModelAccess::create($request->validated());

        return response()->json($access, 201);
    }

    /**
     * 更新模型访问权限
     */
    public function update(UpdateModelAccessRequest $request, ModelAccess $modelAccess)
    {
        $modelAccess->update($request->validated());

        return response()->json($modelAccess);
    }

    /**
     * 删除模型访问权限
     */
    public function destroy(ModelAccess $modelAccess)
    {
        $modelAccess->delete();

        return response()->json(null, 204);
    }
}
