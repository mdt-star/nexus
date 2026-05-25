# 当前活动上下文

## 当前状态

所有 144 个测试已全部通过（285 个断言），2 个 deprecation 警告为 Laravel 框架自身产生。

## 最近变更

### 1. MountInstance 问题修复（execute 双层 groupStack 嵌套）
- **问题**：`MountInstance::execute()` 原来用 `Route::group(['middleware' => [...]])` 包裹 + `resolver()->group()` 内层 = 两层 groupStack 嵌套，造成 prefix 和 middleware 翻倍，路由 URL 变为 `/prefix/prefix/uri`（404）。
- **修复**：`execute()` 改为直接用 `Route::group()` 一次性注入所有属性（prefix、middleware、defaults），进入回调前重置 RouteRegistrar 的 prefix 为空。  
  → 单层 groupStack，属性不会再翻倍。

### 2. VerifyAuthTagMiddleware 默认中间件静默通过
- **问题**：mount 级默认注入的 `auth.tag`（无参数）先于路由级 `auth.tag:custom:tag` 执行。对于无控制器的闭包路由，默认的 `auth.tag` 无法推断 tag，抛 `tag_not_found`。
- **修复**：记录原始参数 `$hasExplicitParams`，无显式参数时若无法推断 tag 则 `$next($request)` 静默通过，由路由级更具体的 `auth.tag:xxx` 完成检查。

### 3. VerifyAuthTagMiddleware 从 action['defaults'] 读取 defaults
- **问题**：`Route::group(['defaults' => [...]])` 注入的 defaults 通过 `RouteGroup::merge` 存到 `$route->action['defaults']`，而非 `$route->defaults` 属性。中间件中 `$route->defaults['package_id'] ?? null` 永远拿不到 mount 注入的 defaults。
- **修复**：读取时同时检查 `$route->defaults` 和 `$route->action['defaults']`。  
  `$packageId = $route?->defaults['package_id'] ?? $route?->action['defaults']['package_id'] ?? null`

## 待办/后续建议

- [x] MountManager::resolveMount() 增加 defaults 合并逻辑
- [x] MountInstance::execute() 单层 Route::group 注入所有属性
- [x] VerifyAuthTagMiddleware 静默通过无参数默认中间件
- [x] VerifyAuthTagMiddleware 兼容 action['defaults'] 路径
