<?php

/**
 * Nexus 静态配置文件
 *
 * 静态配置放 config，动态配置放数据库 configs 表
 */
return [

    /*
     * 功能权限标记配置（permissions 表）
     *
     * 控制前后端路由、页面元素的功能标记（Feature Flag）。
     * 与 model_access（模型访问权限）不同，本配置控制的是"能不能看到/访问"。
     */
    'permissions' => [
        // 是否启用权限校验中间件
        'enabled' => true,

        // 权限缓存 TTL（秒），0 表示不缓存
        'cache_ttl' => 3600,
    ],

    /*
     * 模型访问权限配置（model_accesses 表）
     *
     * 控制主体（用户/角色/团队等）对特定模型的读写删权限及数据范围。
     * 与 permissions（功能标记）不同，本配置控制的是"能不能查/改/删某类数据"。
     */
    'model_access' => [
        // 模型访问权限缓存 TTL（秒），0 表示不缓存
        'cache_ttl' => 3600,
    ],

    /*
     * 菜单配置
     */
    'menus' => [
        // 菜单缓存 TTL（秒），0 表示不缓存
        'cache_ttl' => 3600,

        // 顶级菜单默认图标
        'default_icon' => 'fa-folder',
    ],

    /*
     * 桌面配置
     */
    'desktop' => [
        // 每个用户最大桌面数
        'max_desktops' => 5,

        // 每个桌面最大菜单数
        'max_menus_per_desktop' => 50,
    ],

    /*
     * 数据范围策略配置
     */
    'data_scope' => [
        // 是否全局启用数据范围
        'enabled' => true,
    ],

    /*
     * 动态配置
     */
    'dynamic_config' => [
        // 配置键前缀
        'key_prefix' => 'nexus.',
    ],
];
