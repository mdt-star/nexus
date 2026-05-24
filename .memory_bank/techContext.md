# 技术上下文

## 技术栈
- **框架**: Laravel（扩展包开发模式）
- **语言**: PHP 8.x+（使用 match 表达式、str_starts_with 等 8.x 特性）
- **数据库**: MySQL / MariaDB（零物理外键设计）
- **前端**: 无直接前端，提供 API 接口供前端消费

## 开发环境
- Composer 包管理
- PHPUnit 测试框架
- Laravel Migration 数据库迁移

## 关键依赖
- `illuminate/support` - Laravel 核心支持
- `illuminate/database` - Eloquent ORM
- `illuminate/http` - HTTP 请求/响应
- `illuminate/routing` - 路由系统（RouteRegistrar）

## 包信息
- 包名: `mdt-star/nexus`
- 源码命名空间: `MdtStar\Nexus\`
- 测试命名空间: `MdtStar\Nexus\Tests\`

## 核心组件

### Filterable Trait + FilterRequest
- `Filterable` Trait 提供 `scopeFilter()`，支持 13 种操作符
- `FilterRequest` 抽象基类通过 `filterRules()` 声明式定义白名单
- 支持默认值、请求参数验证、权限控制
- 职责分离：Request 层控制"能查什么"，Trait 层控制"怎么查"

### TypedValueCast（Custom Cast）
- 根据 Config 模型的 type 字段动态转换 value 类型
- 支持 boolean / number / json / null / string
- 写入时自动推断类型并序列化

### DynamicConfigManager
- 动态配置读写，支持运行时修改
- `mergeIntoConfig()` 将数据库配置合并到 Laravel Config Repository
- `set()` 写入后自动 `config()->set()` 即时生效
- `delete()` 删除后恢复静态配置值

### MountManager + MountInstance（路由挂载系统）
- `MountManager` 管理 mount 注册、解析、继承、能力
- `MountInstance` 是 RouteRegistrar 的代理，支持链式取消能力
- `__call` 规则：
  - `without{Ability}` + 能力已注册 → 加入取消列表，重置路由
  - `without{Ability}` + 能力未注册 → 转给 RouteRegistrar（用户 macro 有机会处理）
  - 非 `without` 方法（get/post/group/middleware/name 等）→ 转给 RouteRegistrar
- 回调统一接收 `MountInstance` 作为 `$route` 参数
- 快捷宏自动注册（如 `Route::api(...)` 等价于 `Route::mount('api', ...)`）

## 设计约束
1. 零物理外键 - 所有表间关联为逻辑关联
2. 配置驱动 - 静态配置放 config，动态配置放数据库
3. 模块化 - 第三方模块即装即用
4. 单一职责 - 每个类只做一件事
5. 多国语言支持 - name 通过语言包匹配 tag，不存数据库
6. 声明式过滤 - 过滤规则在 Request 层声明，Model 层不参与

## 文档编写约束

- **设计文档禁码**：架构设计文档（`docs/design/`）只描述设计思路、规则、策略和决策，严禁出现具体代码实现。代码应放在 `src/` 目录中。
