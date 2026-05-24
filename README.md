# Nexus — Laravel 核心权限枢纽

Laravel 核心权限模块，提供功能权限控制、菜单管理、数据范围策略、动态配置等基础设施能力。

## 功能特性

- **功能权限控制** - 基于 tag 的权限节点体系，支持树形层级结构
- **菜单管理** - 菜单发布池，支持动态路径拼接和用户自定义覆盖
- **用户桌面** - 支持用户自定义桌面布局，从菜单池拖拽绑定
- **数据范围策略** - 可扩展的数据范围控制，支持模型白名单和字段白名单
- **动态配置** - 运行时动态配置管理，支持类型自省
- **模型权限矩阵** - 模型级别的读写删权限控制
- **模块化集成** - 第三方模块即装即用，自动注册权限和菜单
- **多国语言** - 权限名称通过语言包匹配 tag，不存储在数据库

## 安装

```bash
composer require mdt-star/nexus
```

## 配置

发布配置文件：

```bash
php artisan vendor:publish --tag=nexus-config
```

发布语言文件：

```bash
php artisan vendor:publish --tag=nexus-lang
```

## 快速开始

### 1. 权限同步

同步单个模块的权限和菜单：

```bash
php artisan nexus:sync-permissions --package=third-party/module-article
```

### 2. 路由权限控制

使用 `Route::auth()` 路由组自动注入权限校验：

```php
// 自动推断包名（推荐）
Route::auth(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
});

// 显式指定包名
Route::auth('third-party/module-article', function () {
    Route::get('/articles', [ArticleController::class, 'index']);
});

// 自定义 tag
Route::auth(function () {
    Route::get('/custom', [CustomController::class, 'index'])->tag('custom:tag');
});
```

中间件参数格式：`auth.tag:{tag},{guard?}`

```php
// 指定 tag 和 guard
Route::get('/global', ...)->middleware('auth.tag:global:tag,web');
```

### 3. 路由挂载系统（Mount System）

路由挂载系统提供了一种声明式、可继承的路由组织方式。通过预定义"挂载点"，快速注册带有统一前缀和中间件能力的路由组。

**基础使用：**
```php
// 回调接收 $route 参数（MountInstance 实例）
Route::mount('api', function ($route) {
    $route->get('/users', [UserController::class, 'index']);
});

// 指定版本
Route::mount('api:v2', function ($route) {
    $route->get('/users', [UserController::class, 'index']);
});
```

**快捷宏（自动注册）：**
```php
// 等价于 Route::mount('api', ...)
Route::api(function ($route) {
    $route->get('/users', [UserController::class, 'index']);
});
```

**取消能力：**
```php
// 取消 auth 能力（无需认证的路由）
Route::mount('api')->withoutAuth(function ($route) {
    $route->get('/public', [PublicController::class, 'index']);
});

// 链式调用
Route::mount('api')->withoutAuth()->get('/public', function () {
    return 'public';
});
```

**扩展自定义 Mount：**
```php
// 在模块的 ServiceProvider 中注册
Route::extendMount('admin', function (string $version = 'v1') {
    return [
        'extends' => "api:{$version}",
        'prefix' => '/admin',
    ];
});

// 使用
Route::mount('admin', function ($route) {
    $route->get('/dashboard', [DashboardController::class, 'index']);
});
// 等价于：/api/v1/admin/dashboard，带 auth 中间件
```

**扩展能力：**
```php
Route::extendAbility('audit', function ($route) {
    return $route->middleware('audit.log');
});
```

### 4. 数据范围 Trait

在模型中使用数据范围控制：

```php
use MdtStar\Nexus\Scopes\HasDataScope;

class Article extends Model
{
    use HasDataScope;
}
```

### 5. 模型权限控制

在模型中使用模型访问权限：

```php
use MdtStar\Nexus\Contracts\HasModelAccess;
use MdtStar\Nexus\Traits\HasModelAccessTrait;

class Article extends Model implements HasModelAccess
{
    use HasModelAccessTrait;
}
```

### 6. 功能权限接口

模型实现 `HasPermission` 接口即可获得 tag 权限校验能力：

```php
use MdtStar\Nexus\Contracts\HasPermission;
use MdtStar\Nexus\Traits\HasPermissionTrait;

class Admin extends Model implements HasPermission
{
    use HasPermissionTrait;
}
```

User 模型已内置实现，支持穿透 Role 合并 tag：

```php
// 检查用户是否有指定 tag
$user->hasTag('article:list', $packageId);

// 获取用户所有 tag（含角色穿透）
$tags = $user->getPermissionTags();

// 清理权限缓存
$user->flushPermissionCache();
```

## 第三方模块集成

第三方模块在 `composer.json` 中声明权限和菜单：

```json
{
    "extra": {
        "nexus": {
            "permissions": [
                {
                    "tag": "article",
                    "children": [
                        { "tag": "list" },
                        { "tag": "add" },
                        { "tag": "edit" },
                        { "tag": "delete" }
                    ]
                }
            ]
        }
    }
}
```

## 核心概念

| 概念 | 说明 |
|------|------|
| Permission | 功能权限节点，通过 tag 标识 |
| Package | 模块包，关联一组权限 |
| ModelHasPermission | 多态关联，将 tag 授予 User/Role |
| Role | 角色组，支持 tag 穿透到用户 |
| Menu | 菜单发布池，前端可用的菜单项 |
| Desktop | 用户桌面，支持自定义布局 |
| DataScope | 数据范围策略，控制查询数据边界 |
| DynamicConfig | 动态配置，支持运行时修改 |
| ModelAccess | 模型级别的读写删权限控制 |

## 架构设计

### 权限校验流程

```
请求 → VerifyAuthTagMiddleware
  ├─ 1. 认证检查（继承 Authenticate）
  ├─ 2. 获取 tag（三层降级）
  │   ├─ 中间件参数（auth.tag:article:list）
  │   ├─ defaults('auth_tag')（Route::tag()）
  │   └─ 自动推断（控制器名:方法）
  ├─ 3. 获取 package_id（Route::auth() 注入）
  └─ 4. $user->hasTag($tag, $packageId)
       ├─ User 自身 tag（多态查询）
       ├─ Role 穿透 tag（合并去重）
       └─ 缓存（3600s）
```

### 数据范围流程

```
Model::query() → HasDataScope::apply()
  ├─ 1. resolveSubject() 解析作用主体
  ├─ 2. 检查 HasModelAccess 接口
  ├─ 3. 查询 model_accesses 表
  └─ 4. applyScopeStrategy() 应用策略
```

## 设计原则

1. **零物理外键** - 逻辑关联，表结构独立
2. **Laravel 生态兼容** - 遵循命名规范，使用生态工具
3. **配置驱动** - 静态配置放 config，动态配置放数据库
4. **模块化** - 第三方模块即装即用
5. **单一职责** - 每个类只做一件事

## 测试

```bash
php vendor/bin/phpunit
```

当前测试覆盖：82 个测试，145 个断言，涵盖 HasPermissionTrait、PermissionSyncer、Package、PermissionDeniedException、User 穿透 Role 集成测试、Mount 路由挂载系统。

## 许可证

MIT
