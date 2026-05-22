<?php

/**
 * Core Base Module API 路由
 *
 * 所有路由前缀由主应用在 RouteServiceProvider 中统一配置
 */

use Illuminate\Support\Facades\Route;
use MdtStar\Nexus\Http\Controllers\ModelAccessController;
use MdtStar\Nexus\Http\Controllers\SystemConfigController;

// 模型访问权限接口
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
