<?php

namespace MdtStar\Nexus\Http\Middlewares;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use MdtStar\Nexus\Contracts\HasPermission;
use MdtStar\Nexus\Exceptions\PermissionDeniedException;

/**
 * 权限校验中间件
 *
 * 继承 Laravel Authenticate 中间件，自动要求用户登录。
 * 校验当前用户是否拥有指定功能权限 tag，未登录则抛 AuthenticationException。
 * 检查的是 permissions 表中的功能标记（Feature Flag），
 * 与 model_accesses（模型访问权限）无关。
 *
 * 中间件参数格式：auth.tag:{tag},{guard?}
 * - auth.tag:article:list → tag=article:list, guard=api（默认）
 * - auth.tag:article:list,web → tag=article:list, guard=web
 *
 * tag 获取策略（三层降级）：
 * 1. 中间件参数（如 auth.tag:article:list）
 * 2. defaults('auth_tag')（来自 ->tag() 方法）
 * 3. 自动推断（控制器名:方法映射）
 *
 * package_id 获取策略：
 * - 有 defaults('package_id') → 精确查询
 * - 无 → 查询 package_id IS NULL 的全局权限
 *
 * 用法：
 * ```php
 * // 自动推断（推荐，Route::auth() 组内）
 * Route::auth(function () {
 *     Route::get('/articles', [ArticleController::class, 'index']);
 * });
 *
 * // 自定义 tag
 * Route::auth(function () {
 *     Route::get('/custom', ...)->tag('custom:tag');
 * });
 *
 * // 原生中间件
 * Route::get('/global', ...)->middleware('auth.tag:global:tag');
 * ```
 */
class VerifyAuthTagMiddleware extends Authenticate
{
    /**
     * 控制器方法到权限动作的映射
     */
    protected array $actionMap = [
        'index' => 'list',
        'create' => 'add',
        'store' => 'add',
        'show' => 'detail',
        'edit' => 'edit',
        'update' => 'edit',
        'destroy' => 'delete',
    ];

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$params 中间件参数，包含 guard 和 tag
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$params): mixed
    {
        // 从参数中分离 guard 和 tag
        // 已知 guard 名称：web、api；其余视为 tag
        [$guards, $tag] = $this->parseParams($params);

        // 默认 guard 为 api
        if (empty($guards)) {
            $guards = ['api'];
        }

        // 父类认证检查，未登录则抛 AuthenticationException
        $this->authenticate($request, $guards);

        // 第一层：中间件参数中的 tag
        if (! $tag) {
            // 第二层：defaults('auth_tag') — 来自 ->tag() 方法
            $tag = $request->route()?->defaults('auth_tag');
        }

        if (! $tag) {
            // 第三层：从控制器和方法自动推断
            $tag = $this->inferTagFromRoute($request->route());
        }

        if (! $tag) {
            throw new PermissionDeniedException('tag_not_found');
        }

        // 校验当前用户是否被授予了此 tag
        $user = Auth::user();

        if (! $user instanceof HasPermission) {
            throw new PermissionDeniedException('subject_not_has_permission_interface');
        }

        // 超级管理员跳过权限校验
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        $packageId = $request->route()?->defaults('package_id');

        if (! $user->hasTag($tag, $packageId)) {
            throw new PermissionDeniedException('no_tag_permission', ['tag' => $tag]);
        }

        return $next($request);
    }

    /**
     * 从中间件参数中分离 guard 和 tag
     *
     * 已知 guard 名称：web、api
     * 其余参数视为 tag
     *
     * 例如：
     * - ['article:list'] → guards=[], tag='article:list'
     * - ['article:list', 'web'] → guards=['web'], tag='article:list'
     * - ['api'] → guards=['api'], tag=null
     *
     * @param array $params
     * @return array [guards, tag]
     */
    protected function parseParams(array $params): array
    {
        $guards = [];
        $tag = null;

        foreach ($params as $param) {
            if (in_array($param, ['web', 'api'], true)) {
                $guards[] = $param;
            } else {
                $tag = $param;
            }
        }

        return [$guards, $tag];
    }

    /**
     * 从路由的控制器和方法自动推断 tag
     *
     * 规则：
     * - 控制器名去掉 Controller 后缀，首字母小写
     * - 方法名按 actionMap 映射
     * - 组合为 `{控制器}:{动作}`（如 ArticleController@index → article:list）
     *
     * @param Route|null $route
     * @return string|null
     */
    protected function inferTagFromRoute(?Route $route): ?string
    {
        if (! $route) {
            return null;
        }

        $action = $route->getAction();
        $controller = $action['controller'] ?? null;

        if (! $controller || ! str_contains($controller, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $controller);
        $controllerName = lcfirst(str_replace('Controller', '', class_basename($class)));

        $action = $this->actionMap[$method] ?? $method;

        return $controllerName . ':' . $action;
    }
}
