# 当前活动上下文

## 当前状态
- 项目骨架已搭建完成，所有核心功能已实现
- 82 个测试全部通过（145 个断言）
- 路由挂载系统（Mount System）已实现并测试通过

## 最近变更
1. **MountInstance 重构为 RouteRegistrar 代理模式**：
   - `MountInstance` 内部持有 `RouteRegistrar` 实例（懒加载）
   - `__call` 中 `without{Ability}` 能力已注册 → 加入取消列表，重置路由
   - `without{Ability}` 能力未注册 → 转交给 RouteRegistrar（让用户扩展的 macro 有机会处理）
   - 非 `without` 方法（如 `get`, `post`, `group`, `middleware`, `name`）→ 转交给 RouteRegistrar
   - 这样 `Route::mount('api')->withoutAuth()->get('/public', ...)` 也能正常工作

2. **新增 6 个测试验证 RouteRegistrar 转发**：
   - `mount_instance_forwards_get_to_route_registrar` — get() 返回 Route
   - `mount_instance_forwards_post_to_route_registrar` — post() 返回 Route
   - `mount_instance_forwards_middleware_to_route_registrar` — middleware() 返回 RouteRegistrar
   - `mount_instance_forwards_name_to_route_registrar` — name() 返回 RouteRegistrar
   - `mount_instance_forwards_group_to_route_registrar` — group() 返回 RouteRegistrar
   - `mount_instance_chain_get_after_without_auth` — withoutAuth() 后 get() 返回 Route

## 能力继承规则
- **多父级之间**：abilities 取并集（array_merge）
- **子级对父级**：abilities 也是取并集（array_merge），与多父级行为一致
- **取消能力**：使用 `without{Ability}()` 动态调用，能力已注册则取消，未注册则转给 RouteRegistrar

## 前缀合并规则
- **绝对路径**（以 `/` 开头，如 `/admin`）→ 直接替换父级前缀
- **相对路径**（不以 `/` 开头，如 `admin`）→ 追加到父级后面

## 下一步计划
- 无
