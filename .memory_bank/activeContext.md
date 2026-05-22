# 当前活动上下文

## 当前任务
根据设计文档构建项目骨架，当前正在进行架构重构。

## 最近改动
- 项目更名为 Nexus（枢纽）
- 实现持久配置合并机制（DynamicConfigManager + NexusServiceProvider）
- TypedValueCast：Laravel Custom Cast 重构 Config 模型类型转换
- Form Request：Controller 验证逻辑抽离到独立文件
- Filterable Trait + FilterRequest：统一查询过滤体系

## 当前完成改动
- ✅ PermissionSyncer — tag 原样保存，不拼接父级前缀
- ✅ permissions 表唯一约束改为 (package_id, tag, parent_id)
- ✅ Package 全表缓存（allCached() + flushCache()）
- ✅ 新建 model_has_permissions 多态表 + ModelHasPermission 模型
- ✅ 新建 HasPermission 接口 + HasPermissionTrait 默认实现（多态 + 缓存 + hasTag()）
- ✅ User 模型实现 HasPermission，复写 getPermissionTags() 穿透 Role
- ✅ Role 模型实现 HasPermission，使用 trait 默认实现
- ✅ VerifyAuthTagMiddleware 改为调用 $user->hasTag()，异常消息国际化

## 下一步计划
- ⏳ 未来处理 tag 绑定时，需要实现 ModelHasPermission 单个对象级的缓存清空
  - 当给某个模型（User/Role）新增/删除 tag 时，调用该模型的 flushPermissionCache()
  - 可通过 ModelHasPermission 观察者（saved/deleted）自动触发关联模型的缓存清理
  - 避免全量缓存过期，实现精准缓存失效
