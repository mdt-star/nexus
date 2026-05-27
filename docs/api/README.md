# Nexus API 接口文档

所有接口默认挂载在 `/api/v1/admin` 前缀下，需要认证（auth 中间件）。

---

## 1. 模型访问权限 (ModelAccess)

### `GET /api/v1/admin/model-accesses`

获取模型访问权限列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| subject_type | eq | 主体类型（完整类名） |
| subject_id | eq | 主体 ID |
| class | like | 目标模型类名 |
| can_read | eq | 是否可读 |
| can_write | eq | 是否可写 |
| can_delete | eq | 是否可删 |
| scope_key | eq | 数据范围策略 key |

### `POST /api/v1/admin/model-accesses`

创建模型访问权限。

**请求体：**
```json
{
    "subject": "App\\Models\\User@1",
    "class": "App\\Models\\Article",
    "can_read": true,
    "can_write": true,
    "can_delete": false,
    "scope_key": "org_only"
}
```

> `subject` 格式：`完整类名@ID`

### `PUT /api/v1/admin/model-accesses/{id}`

更新模型访问权限。

### `DELETE /api/v1/admin/model-accesses/{id}`

删除模型访问权限。

---

## 2. 动态配置 (SystemConfig)

### `GET /api/v1/admin/system-config`

获取动态配置列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| key | like | 配置键名 |
| type | eq | 类型（string/boolean/json/number） |

### `POST /api/v1/admin/system-config`

创建动态配置。

**请求体：**
```json
{
    "key": "nexus.upload_max_size",
    "value": "10",
    "type": "number",
    "description": "上传最大尺寸（MB）"
}
```

### `PUT /api/v1/admin/system-config/{id}`

更新动态配置。

### `DELETE /api/v1/admin/system-config/{id}`

删除动态配置（恢复为静态配置默认值）。

---

## 3. 桌面管理 (Desktop)

### `GET /api/v1/admin/desktops`

获取桌面列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| user_id | eq | 用户 ID |
| region | eq | 布局区域 |
| is_default | eq | 是否默认桌面 |

### `POST /api/v1/admin/desktops`

创建桌面。

**请求体：**
```json
{
    "user_id": 1,
    "name": "主桌面",
    "region": "sidebar_left",
    "is_default": true
}
```

### `GET /api/v1/admin/desktops/{id}`

获取桌面详情。

### `PUT /api/v1/admin/desktops/{id}`

更新桌面。

### `DELETE /api/v1/admin/desktops/{id}`

删除桌面（同时删除其下的所有桌面项）。

---

## 4. 桌面项管理 (DesktopItem)

### `GET /api/v1/admin/desktops/{desktop}/items`

获取指定桌面的所有项（按 sort 升序排列）。

### `POST /api/v1/admin/desktops/{desktop}/items`

创建桌面项。

**请求体：**
```json
{
    "label": "文章管理",
    "path": "/articles",
    "icon": "article-icon",
    "component": "Article/List",
    "custom": null,
    "sort": 0
}
```

### `GET /api/v1/admin/desktops/{desktop}/items/{item}`

获取桌面项详情。

### `PUT /api/v1/admin/desktops/{desktop}/items/{item}`

更新桌面项。

### `DELETE /api/v1/admin/desktops/{desktop}/items/{item}`

删除桌面项。

### `PUT /api/v1/admin/desktops/{desktop}/items/reorder`

批量排序桌面项。

**请求体：**
```json
{
    "items": [
        { "id": 1, "sort": 0 },
        { "id": 2, "sort": 1 },
        { "id": 3, "sort": 2 }
    ]
}
```

---

## 5. 用户管理 (User)

### `GET /api/v1/admin/users`

获取用户列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| name | like | 用户名 |
| email | like | 邮箱 |

### `POST /api/v1/admin/users`

创建用户。

**请求体：**
```json
{
    "name": "张三",
    "email": "zhangsan@example.com",
    "password": "password123"
}
```

### `GET /api/v1/admin/users/{id}`

获取用户详情。

### `PUT /api/v1/admin/users/{id}`

更新用户。

### `DELETE /api/v1/admin/users/{id}`

删除用户。

---

## 6. 角色管理 (Role)

### `GET /api/v1/admin/roles`

获取角色列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| name | like | 角色名称 |
| slug | eq | 角色标识 |

### `POST /api/v1/admin/roles`

创建角色。

**请求体：**
```json
{
    "name": "编辑",
    "slug": "editor",
    "description": "内容编辑人员"
}
```

### `GET /api/v1/admin/roles/{id}`

获取角色详情。

### `PUT /api/v1/admin/roles/{id}`

更新角色。

### `DELETE /api/v1/admin/roles/{id}`

删除角色（自动解除与用户的关联）。

---

## 7. 功能权限标记管理 (Permission)

### `GET /api/v1/admin/permissions`

获取权限列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| tag | like | 权限标识 |
| package_id | eq | 包 ID |
| parent_id | eq | 父级 ID |

### `POST /api/v1/admin/permissions`

创建权限。

**请求体：**
```json
{
    "tag": "article:list",
    "package_id": 1,
    "parent_id": null
}
```

### `GET /api/v1/admin/permissions/{id}`

获取权限详情。

### `PUT /api/v1/admin/permissions/{id}`

更新权限。

### `DELETE /api/v1/admin/permissions/{id}`

删除权限。

---

## 8. 已授权权限管理 (Permissionable)

### `GET /api/v1/admin/permissionables`

获取已授权权限列表。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| model_type | eq | 主体类型（完整类名） |
| model_id | eq | 主体 ID |
| tag | like | 权限标识 |
| package_id | eq | 包 ID |

### `POST /api/v1/admin/permissionables`

授予权限。

**请求体：**
```json
{
    "model": "App\\Models\\User@1",
    "tag": "article:list",
    "package_id": 1
}
```

> `model` 格式：`完整类名@ID`

### `DELETE /api/v1/admin/permissionables/{id}`

撤销权限。

---

## 9. 包管理 (Package)

### `GET /api/v1/admin/packages`

获取包列表（只读）。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| name | like | 包名 |

---

## 10. 数据范围策略 (ModelScope)

### `GET /api/v1/admin/model-scopes`

获取数据范围策略列表（只读）。

**过滤参数：**
| 参数 | 操作符 | 说明 |
|------|--------|------|
| key | like | 策略 key |
| class | like | 策略类名 |

---

## 通用说明

### 过滤参数格式

所有 `GET` 接口支持统一的过滤参数格式：

```
?user_id=1                    → eq 操作符
?name=张                      → like 操作符（自动模糊匹配）
?name:like=张                 → 显式指定操作符
?created_at:gt=2024-01-01     → 大于
?created_at:between=2024-01-01,2024-12-31  → 范围
```

### 响应格式

成功响应直接返回数据或模型实例。

错误响应：
```json
{
    "message": "权限不足",
    "exception": "PermissionDeniedException"
}
```

### subject/model 格式说明

涉及多态关联的接口（ModelAccess、Permissionable），使用 `完整类名@ID` 格式：

| 格式 | 示例 | 说明 |
|------|------|------|
| `App\Models\User@1` | 用户 ID=1 | subject_type=App\Models\User, subject_id=1 |
| `App\Models\Role@3` | 角色 ID=3 | subject_type=App\Models\Role, subject_id=3 |
