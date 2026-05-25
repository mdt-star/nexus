# 当前活动上下文

## 当前断点

全部 135 个测试通过（270 个断言），2 个 Deprecation 警告（PHP 8.5+ 反射方法无需 setAccessible）。

## 已完成改动

### 重构：去掉能力系统 + instance，改用 middlewares 声明式配置

**背景**：`MountInstance::resolver()` 中逐个应用能力时，`RouteRegistrar` 的 `attribute('middleware', ...)` 会覆盖之前的 middleware 值，导致中间件丢失。之前用反射收集所有能力的中间件再一次性应用，但反射方案不够简洁。

**改动**：
1. **去掉能力系统**：移除 `MountManager::extendAbility()`、`hasAbility()`、`applyAbility()` 方法
2. **去掉 instance 回调**：mount 配置不再支持 `instance` 回调
3. **去掉 without 机制**：`MountInstance` 不再支持 `withoutAuth()` 等链式取消
4. **改用 middlewares 配置**：mount 配置中 `abilities` → `middlewares`，直接声明中间件列表
5. **简化 MountInstance**：`resolver()` 中直接 `$this->route->middleware(array_unique($config['middlewares']))`，不需要反射

**影响文件**：
- `src/Routing/MountInstance.php`：199 行 → 65 行
- `src/Routing/MountManager.php`：345 行 → 270 行
- `src/Providers/NexusServiceProvider.php`：去掉能力注册，`abilities` → `middlewares`
- `tests/Unit/MountManagerTest.php`：去掉能力/without 相关测试，更新为 middlewares

**开发者取消中间件**：在子路由中用 Laravel 原生的 `withoutMiddleware()`：
```php
Route::mount('api', function ($route) {
    $route->withoutMiddleware('auth.tag')->group(function () {
        $route->get('/public', [PublicController::class, 'index']);
    });
});
```

### 修复：隐式模型绑定不生效
- **根因**：`Route::admin()` 注册的路由只应用了 `auth.tag` 中间件，缺少 `SubstituteBindings` 中间件
- **修复**：在 `auth` 能力中添加 `SubstituteBindings::class`
- **影响**：`DesktopItemController` 等依赖模型绑定的控制器恢复正常

### 修复：Filterable like 操作符缺少通配符
- **根因**：`Filterable::applyFilter` 中 `like` 操作符直接使用原始值，未自动添加 `%` 通配符
- **修复**：`like` 操作符自动在值两侧添加 `%`

### 清理：移除调试代码
- 移除 `DesktopController::update()` 中的 `dump()` 调试语句
- 移除 `DesktopApiTest::更新桌面()` 中的 `dump()` 调试语句

### 新增：VerifyAuthTagMiddleware 权限标签检查集成测试
- 新增 `tests/Feature/AuthTagMiddlewareTest.php`，14 个测试覆盖：
  - 未登录 → 401
  - 超级管理员跳过权限检查
  - 有权限的用户可以访问
  - 无权限的用户被拒绝
  - 中间件参数指定 tag（有/无权限）
  - Route::tag() 自定义 tag（有/无权限）
  - 无 tag 可推断时抛 tag_not_found
  - 用户未实现 HasPermission 接口被拒绝
  - package_id 精确匹配（有/无权限）
  - 全局 tag（package_id IS NULL）匹配
  - 角色 tag 穿透到用户

### 桌面项支持树状结构
- `create_desktop_items_table.php` 迁移增加 `parent_id` 字段（自引用外键，级联删除）
- `DesktopItem` 模型增加 `parent()` 和 `children()` 关联
- `DesktopItemController::index()` 返回树状结构（根节点 + with('children')）
- `StoreDesktopItemRequest` / `UpdateDesktopItemRequest` 增加 `parent_id` 验证
- `TestCase` 启用 SQLite 外键约束（`PRAGMA foreign_keys = ON`）
- 新增 4 个测试：创建子级项、树状列表、更新 parent_id、级联删除

### 发布所有接口 tag 到 composer.json
- `composer.json` 的 `extra.nexus.permissions` 声明所有 API 接口的 tag 树
- 覆盖：system, model-access, desktop, desktop-item, user, role, permission, permissionable, package, model-scope
- 同步更新 `lang/zh_CN/permissions.php` 和 `lang/en/permissions.php` 的 tag 名称映射

## 测试状态
- 135 个测试全部通过（270 个断言）
