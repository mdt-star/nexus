# 项目进度

## 已完成
- [x] 项目骨架搭建完成
- [x] 数据库迁移文件（12 张表，含 users）
- [x] 模型、服务、控制器、中间件
- [x] 权限系统（HasModelAccess + HasDataScope + HasPermission）
- [x] 动态配置合并机制
- [x] TypedValueCast 类型转换
- [x] Form Request 验证
- [x] Filterable 查询过滤
- [x] 项目更名为 Nexus
- [x] PermissionSyncer 重构（tag 原样保存，不拼接父级前缀）
- [x] VerifyAuthTagMiddleware 三层降级推断 + 国际化异常
- [x] Route::auth() + Route::tag() 路由宏
- [x] HasPermission 接口 + HasPermissionTrait（多态 + 缓存 + hasTag）
- [x] User 模型实现 HasPermission，穿透 Role 合并 tag
- [x] Role 模型实现 HasPermission
- [x] ModelHasPermission 观察者备忘（单对象级缓存清空）
- [x] 测试覆盖（33 个测试全部通过）
- [x] 解决多角色权限冲突问题
- [x] 51 个测试全部通过
- [x] Route Mount 系统实现
  - [x] 创建 MountManager 核心类
  - [x] 创建 MountInstance 链式调用类
  - [x] 注册 Route::mount() 宏
  - [x] 注册 Route::extendMount() 宏
  - [x] 注册 Route::extendAbility() 宏
  - [x] 快捷宏自动注册
  - [x] withoutAuth 链式调用
  - [x] 预定义 api mount + auth ability
- [x] 超级管理员
  - [x] User::isSuperAdmin()（可配置 super_admin_id）
  - [x] VerifyAuthTagMiddleware 超级管理员放行
  - [x] HasDataScope 超级管理员跳过
- [x] 更新路由文件使用新的 mount 系统
- [x] 编写 Mount 系统测试（17 个测试全部通过）
- [x] 全部 68 个测试通过
- [x] 更新设计文档（Mount 系统 + 超级管理员章节）
- [x] MountInstance 重构为 RouteRegistrar 代理模式
  - [x] 内部持有 RouteRegistrar 实例（懒加载）
  - [x] without{Ability} 能力已注册 → 加入取消列表
  - [x] without{Ability} 能力未注册 → 转给 RouteRegistrar
  - [x] 非 without 方法（get/post/group 等）→ 转给 RouteRegistrar
  - [x] $route 参数传递机制（回调接收 MountInstance）
- [x] 全部 82 个测试通过（145 个断言）

## 待办
- [ ] 无
