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
  - HasPermissionTrait 单元测试（8 个）
  - PermissionSyncer 单元测试（7 个）
  - Package 单元测试（5 个）
  - PermissionDeniedException 单元测试（5 个）
  - User 穿透 Role 集成测试（6 个）
- [x] 更新设计文档

## 进行中
- [ ] 完善使用文档（README）
