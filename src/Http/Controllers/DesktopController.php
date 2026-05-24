<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\Desktop;
use MdtStar\Nexus\Http\Requests\StoreDesktopRequest;
use MdtStar\Nexus\Http\Requests\UpdateDesktopRequest;
use MdtStar\Nexus\Http\Requests\DesktopFilterRequest;
use Illuminate\Routing\Controller;

/**
 * 桌面管理接口
 */
class DesktopController extends Controller
{
    public function index(DesktopFilterRequest $request)
    {
        return Desktop::filter($request->filters())->get();
    }

    public function store(StoreDesktopRequest $request)
    {
        return Desktop::create($request->validated());
    }

    public function show(Desktop $desktop)
    {
        return $desktop;
    }

    public function update(UpdateDesktopRequest $request, Desktop $desktop)
    {
        $desktop->update($request->validated());
        return $desktop;
    }

    public function destroy(Desktop $desktop)
    {
        $desktop->items()->delete();
        $desktop->delete();
        return response()->noContent();
    }
}
