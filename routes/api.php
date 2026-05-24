<?php

/**
 * Nexus 核心包 API 路由
 *
 * 使用 Route Mount 系统注册路由。
 * 所有路由自动挂载到 /api/{version} 前缀并应用 auth 能力。
 *
 * 版本控制：
 * - 默认版本 v1
 * - 可通过 Route::mount('api:v2', ...) 切换版本
 */

use Illuminate\Support\Facades\Route;
use MdtStar\Nexus\Http\Controllers\ModelAccessController;
use MdtStar\Nexus\Http\Controllers\SystemConfigController;

// 模型访问权限接口
Route::mount('api', function () {
    Route::apiResource('model-accesses', ModelAccessController::class)->except(['show']);

    // 管理端模型访问权限接口（支持更多过滤字段）
    Route::get('admin/model-accesses', [ModelAccessController::class, 'adminIndex']);

    // 动态配置接口
    Route::prefix('system-config')->group(function () {
        Route::get('/', [SystemConfigController::class, 'index']);
        Route::post('/', [SystemConfigController::class, 'store']);
        Route::put('/{config}', [SystemConfigController::class, 'update']);
        Route::delete('/{config}', [SystemConfigController::class, 'destroy']);
    });
});
