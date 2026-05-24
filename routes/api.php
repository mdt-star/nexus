<?php

/**
 * Nexus 核心包 API 路由
 *
 * 使用 Route Mount 系统注册路由。
 * 所有路由自动挂载到 /api/{version} 前缀并应用 auth 能力。
 * admin 域继承 api 域，路由前缀为 /api/{version}/admin。
 *
 * 版本控制：
 * - 默认版本 v1
 * - 可通过 Route::mount('api:v2', ...) 切换版本
 */

use Illuminate\Support\Facades\Route;
use MdtStar\Nexus\Http\Controllers\ModelAccessController;
use MdtStar\Nexus\Http\Controllers\SystemConfigController;
use MdtStar\Nexus\Http\Controllers\DesktopController;
use MdtStar\Nexus\Http\Controllers\DesktopItemController;
use MdtStar\Nexus\Http\Controllers\UserController;
use MdtStar\Nexus\Http\Controllers\RoleController;
use MdtStar\Nexus\Http\Controllers\PermissionController;
use MdtStar\Nexus\Http\Controllers\PermissionableController;
use MdtStar\Nexus\Http\Controllers\PackageController;
use MdtStar\Nexus\Http\Controllers\ModelScopeController;

// 所有管理端接口统一使用 Route::admin()
Route::admin(function () {

    // 模型访问权限
    Route::apiResource('model-accesses', ModelAccessController::class)->except(['show']);

    // 动态配置
    Route::prefix('system-config')->group(function () {
        Route::get('/', [SystemConfigController::class, 'index']);
        Route::post('/', [SystemConfigController::class, 'store']);
        Route::put('/{config}', [SystemConfigController::class, 'update']);
        Route::delete('/{config}', [SystemConfigController::class, 'destroy']);
    });

    // 桌面管理
    Route::apiResource('desktops', DesktopController::class);

    // 桌面项管理（嵌套在桌面下）
    Route::put('desktops/{desktop}/items/reorder', [DesktopItemController::class, 'reorder']);
    Route::get('desktops/{desktop}/items', [DesktopItemController::class, 'index']);
    Route::post('desktops/{desktop}/items', [DesktopItemController::class, 'store']);
    Route::get('desktops/{desktop}/items/{item}', [DesktopItemController::class, 'show']);
    Route::put('desktops/{desktop}/items/{item}', [DesktopItemController::class, 'update']);
    Route::delete('desktops/{desktop}/items/{item}', [DesktopItemController::class, 'destroy']);

    // 用户管理
    Route::apiResource('users', UserController::class);

    // 角色管理
    Route::apiResource('roles', RoleController::class);

    // 功能权限标记管理
    Route::apiResource('permissions', PermissionController::class);

    // 已授权权限管理（授予/撤销）
    Route::apiResource('permissionables', PermissionableController::class)->except(['show', 'update']);

    // 包管理（只读）
    Route::apiResource('packages', PackageController::class)->only(['index']);

    // 数据范围策略（只读）
    Route::apiResource('model-scopes', ModelScopeController::class)->only(['index']);
});
