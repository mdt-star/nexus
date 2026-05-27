<?php

/**
 * Nexus 静态配置文件
 *
 * 静态配置放 config，动态配置放数据库 configs 表
 */
return [

    /*
     * 超级管理员配置
     *
     * 超级管理员拥有至高无上的权限，可以跳过所有权限检查。
     * 默认为系统中第一个用户（id = 1）。
     */
    'super_admin_id' => env('NEXUS_SUPER_ADMIN_ID', 1),

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
     * 桌面配置
     */
    'desktop' => [
        // 每个用户最大桌面数
        'max_desktops' => 5,

        // 每个桌面最大项数
        'max_items_per_desktop' => 50,
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

    /*
     * 参数字段风格转换（Case Middleware）
     *
     * 控制请求参数和响应 JSON 的 key 命名风格。
     * 前端可通过 Header 或参数声明使用 camelCase 还是 snake_case。
     *
     * 检测优先级：
     * 1. Header（如 X-Case: camel）
     * 2. 请求参数（如 _case=camel）
     * 3. 以下 default 配置
     */
    'case' => [
        // 默认风格：snake 或 camel
        'default' => 'camel',

        // 前端通过 Header 声明风格时的 Header 名称
        'header_name' => 'X-Case',

        // 前端通过请求参数声明风格时的参数名称
        'parameter_name' => '_case',
    ],
];
