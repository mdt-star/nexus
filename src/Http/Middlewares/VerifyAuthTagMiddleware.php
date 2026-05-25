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
     * @param string ...$guards 中间件参数，包含 guard 和 tag
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards): mixed
    {
        // 记录原始参数是否为空，用于判断是 mount 级默认中间件还是显式中间件
        $hasExplicitParams = ! empty($guards);

        // 从参数中分离 guard 和 tag
        // 已知 guard 名称：web、api；其余视为 tag
        [$guards, $tag] = $this->parseParams($guards);

        // 默认 guard 为 web（兼容测试环境无 api guard 的场景）
        if (empty($guards)) {
            $guards = ['api', 'web'];
        }

        // 父类认证检查，未登录则抛 AuthenticationException
        $this->authenticate($request, $guards);

        // 第一层：中间件参数中的 tag
        if (! $tag) {
            // 第二层：route defaults 中的 auth_tag — 来自 ->tag() 方法
            $route = $request->route();
            $tag = $route?->defaults['auth_tag'] ?? $route?->action['defaults']['auth_tag'] ?? null;
        }

        if (! $tag) {
            // 第三层：从控制器和方法自动推断
            $tag = $this->inferTagFromRoute($request->route());
        }

        // 重要策略：无原始参数时说明是 mount 级默认注入的 auth.tag（无参数形式），
        // 此时如果无法推断 tag，静默通过让路由级 auth.tag:{tag} 做主检查；
        // 有显式参数时（auth.tag:xxx），找不到 tag 才抛异常。
        if (! $tag) {
            if (! $hasExplicitParams) {
                return $next($request);
            }
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

        $route = $request->route();
        $packageId = $route?->defaults['package_id'] ?? $route?->action['defaults']['package_id'] ?? null;
        $packageName = $route?->defaults['package_name'] ?? $route?->action['defaults']['package_name'] ?? null;

        // package_name 可用于解析 package_id（如果没直接传 package_id 的话）
        // 使用 Package::idByName() 全表缓存 O(1) 查找，无性能开销
        if ($packageId === null && $packageName !== null) {
            $packageId = \MdtStar\Nexus\Models\Package::idByName($packageName);
        }

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
