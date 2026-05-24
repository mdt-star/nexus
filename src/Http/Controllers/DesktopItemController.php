<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\Desktop;
use MdtStar\Nexus\Models\DesktopItem;
use MdtStar\Nexus\Http\Requests\StoreDesktopItemRequest;
use MdtStar\Nexus\Http\Requests\UpdateDesktopItemRequest;
use MdtStar\Nexus\Http\Requests\ReorderDesktopItemsRequest;
use Illuminate\Routing\Controller;

/**
 * 桌面项管理接口
 *
 * 支持 CRUD 和批量排序。
 */
class DesktopItemController extends Controller
{
    public function index(Desktop $desktop)
    {
        return $desktop->items()->orderBy('sort')->get();
    }

    public function store(StoreDesktopItemRequest $request, Desktop $desktop)
    {
        return $desktop->items()->create($request->validated());
    }

    public function show(Desktop $desktop, DesktopItem $item)
    {
        return $item;
    }

    public function update(UpdateDesktopItemRequest $request, Desktop $desktop, DesktopItem $item)
    {
        $item->update($request->validated());
        return $item;
    }

    public function destroy(Desktop $desktop, DesktopItem $item)
    {
        $item->delete();
        return response()->noContent();
    }

    /**
     * 批量排序桌面项
     *
     * 请求体：[{ "id": 1, "sort": 0 }, { "id": 2, "sort": 1 }]
     */
    public function reorder(ReorderDesktopItemsRequest $request, Desktop $desktop)
    {
        $items = $request->validated()['items'] ?? $request->validated();

        foreach ($items as $item) {
            DesktopItem::where('id', $item['id'])
                ->where('desktop_id', $desktop->id)
                ->update(['sort' => $item['sort']]);
        }

        return $desktop->items()->orderBy('sort')->get();
    }
}
