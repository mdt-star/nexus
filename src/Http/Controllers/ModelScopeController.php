<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\ModelScope;
use MdtStar\Nexus\Http\Requests\ModelScopeFilterRequest;
use Illuminate\Routing\Controller;

/**
 * 数据范围策略接口（只读）
 */
class ModelScopeController extends Controller
{
    public function index(ModelScopeFilterRequest $request)
    {
        return ModelScope::filter($request->filters())->get();
    }
}
