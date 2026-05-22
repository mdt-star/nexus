<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Http\Requests\StoreSystemConfigRequest;
use MdtStar\Nexus\Http\Requests\SystemConfigFilterRequest;
use MdtStar\Nexus\Models\Config;
use MdtStar\Nexus\Services\DynamicConfigManager;
use Illuminate\Routing\Controller;

/**
 * 动态配置接口
 *
 * 提供动态配置的 CRUD 操作。
 */
class SystemConfigController extends Controller
{
    protected DynamicConfigManager $configManager;

    public function __construct(DynamicConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * 获取所有动态配置
     *
     * 支持按 key、type 过滤。
     */
    public function index(SystemConfigFilterRequest $request)
    {
        return response()->json(
            Config::filter($request->filters())->paginate()
        );
    }

    /**
     * 创建动态配置
     */
    public function store(StoreSystemConfigRequest $request)
    {
        $validated = $request->validated();

        $config = $this->configManager->set(
            $validated['key'],
            $validated['value'],
            $validated['description'] ?? null
        );

        return response()->json($config, 201);
    }

    /**
     * 更新动态配置
     */
    public function update(StoreSystemConfigRequest $request, Config $config)
    {
        $validated = $request->validated();

        $updated = $this->configManager->set(
            $config->key,
            $validated['value'],
            $validated['description'] ?? $config->description
        );

        return response()->json($updated);
    }

    /**
     * 删除动态配置
     */
    public function destroy(Config $config)
    {
        $this->configManager->delete($config->key);

        return response()->json(null, 204);
    }
}
