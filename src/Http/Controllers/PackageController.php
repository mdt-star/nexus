<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Http\Requests\PackageFilterRequest;
use Illuminate\Routing\Controller;

/**
 * 包管理接口（只读）
 */
class PackageController extends Controller
{
    public function index(PackageFilterRequest $request)
    {
        return Package::filter($request->filters())->get();
    }
}
