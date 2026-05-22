# 系统模式

## 设计模式

### 服务提供者模式
- `NexusServiceProvider` 作为扩展包入口，负责注册绑定、加载配置、注册事件、自动检测包变更

### 观察者模式
- `PermissionObserver` 监听权限模型变更事件

### 策略模式
- `DataScopeStrategyInterface` 定义数据范围策略契约
- 具体策略类实现数据过滤逻辑

### Trait 混入模式
- `HasDataScope` Trait — 注入到模型中，自动添加数据范围查询条件
- `HasPermissionsTrait` Trait — 注入到模型中，支持权限穿透（用户 → 角色组）
- `HasModelAccessTrait` Trait — 注入到模型中，支持模型访问权限检查
- `Filterable` Trait — 注入到模型中，支持统一查询过滤

### 中间件模式
- `VerifyAuthTagMiddleware` 校验用户权限 tag

### 自定义 Cast 模式
- `TypedValueCast` — 根据 type 字段动态转换 value 类型

### 表单请求模式（Form Request）
- `FilterRequest` 抽象基类 — 统一查询过滤的声明式规则定义
- 具体 Request 类 — 定义字段白名单、操作符、验证规则、默认值

## 关键交互

### 权限同步
```
PermissionSyncer
    → 读取模块 composer.json extra.nexus
    → 递归解析 permissions 树
    → 写入 permissions 表（保持 parent_id 层级）
    → 写入 menus 表（有 route 配置的节点）
```

### 菜单树构建
```
MenuTreeBuilder
    → 从 menus 表读取所有记录
    → 递归构建树形结构
    → 动态拼接完整 path
    → 合并 desktop_menus 的 custom 覆盖值
```

### 数据范围控制
```
HasDataScope (Trait)
    → 自动注入 scope 条件到 Eloquent 查询
    → 处理字段别名映射
    → 读写删操作权限熔断
    → 抛出 PermissionDeniedException（403）
```

### 统一查询过滤
```
FilterRequest (定义规则)
    → filterRules() 声明字段、操作符、验证、默认值
    → filters() 解析请求参数 + 应用默认值
    → authorize() 控制访问权限

Filterable Trait (执行查询)
    → scopeFilter() 遍历过滤条件
    → applyFilter() 根据操作符构建 SQL
    → 支持 13 种操作符
```

### 持久配置合并
```
NexusServiceProvider::boot()
    → mergePersistentConfig()
    → DynamicConfigManager::mergeIntoConfig()
    → config()->set() 覆盖静态配置
    → config('nexus.*') 返回合并值
```

## 命名规范
- 模型: 单数 PascalCase
- 控制器: PascalCase + Controller
- 中间件: PascalCase + Middleware
- 服务类: PascalCase + Manager/Service
- Trait: PascalCase
- 接口: PascalCase + Interface
- 命令: PascalCase + Command
- 迁移: `create_{table}_table.php`
- 配置: kebab-case
- 请求: PascalCase + Request
- 过滤请求: PascalCase + FilterRequest
- Cast: PascalCase + Cast
