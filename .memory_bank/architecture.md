# 架构设计

## 分层架构

```
入口层 (Providers)
    ↓
服务层 (Services)
    ↓
模型层 (Models)
    ↓
数据层 (Migrations/Tables)
```

### 入口层
- `NexusServiceProvider` - 注册观察者、注入 DataScope、加载动态配置、发布配置和语言文件、注册 Builder Macro、自动检测包变更

### 请求层
- `FilterRequest` (抽象基类) - 定义可过滤字段白名单、操作符、验证规则、默认值
- `ModelAccessFilterRequest` / `AdminModelAccessFilterRequest` / `SystemConfigFilterRequest` - 具体过滤请求
- `StoreModelAccessRequest` / `UpdateModelAccessRequest` / `StoreSystemConfigRequest` - 表单验证请求

### 服务层
- `PermissionSyncer` - 解析模块 composer.json 配置，同步权限和菜单
- `MenuTreeBuilder` - 从 menus 表构建树形结构，动态拼接 path
- `DynamicConfigManager` - 动态配置管理器，支持运行时修改和持久配置合并

### 模型层
- Permission / Menu / Desktop / DesktopMenu / Config / ModelScope / ModelAccess / Package / PackageRelation / User / Role
- 使用 Filterable Trait 支持统一查询过滤
- 使用 HasDataScope Trait 支持数据范围控制
- 使用 HasPermissionsTrait 支持权限穿透

### 数据层
- 11 张核心业务表，零物理外键

## 核心流程

### 模块集成流程
```
模块安装 → composer.json extra.nexus 被扫描
    → PermissionSyncer 解析配置 → 落库 permissions + menus 表
    → 模块卸载时清理关联数据
```

### 菜单工作流程
```
模块安装 → 解析配置 → 落库 permissions + menus
    → 管理员可见所有 menus → 用户拖拽到桌面 (desktop_menus)
    → 前端请求 → 后端动态拼接 path → 返回完整菜单树
```

### 统一查询过滤流程
```
HTTP 请求参数
    → FilterRequest::filterRules() 白名单校验
    → FilterRequest::filters() 结构化解析（含默认值）
    → Model::scopeFilter() 构建 SQL 查询
    → 返回过滤后的结果
```

### 持久配置合并流程
```
应用启动 → NexusServiceProvider::boot()
    → mergePersistentConfig() 检查 configs 表
    → DynamicConfigManager::mergeIntoConfig()
    → 数据库配置覆盖静态配置 → config('nexus.*') 返回合并值
```

## 设计原则
1. 零物理外键
2. Laravel 生态兼容
3. 配置驱动
4. 模块化
5. 单一职责
6. 声明式过滤（FilterRequest 定义规则，Filterable 执行查询）
