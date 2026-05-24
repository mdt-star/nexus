<?php

namespace MdtStar\Nexus\Routing;

use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/**
 * 路由挂载管理器（Mount Manager）
 *
 * 管理路由域（Mount）的注册、继承、能力扩展和解析。
 *
 * 核心概念：
 * - Mount = 前缀 + 一组底层能力（abilities）
 * - 基础包预定义 api mount
 * - 开发者通过 extendMount() 注册自己的 mount
 * - mount 可以继承一个或多个 mount
 * - 每个能力可单独通过 withoutXxx() 取消
 *
 * 使用示例：
 * ```php
 * // 基础使用
 * Route::mount('api', function () { ... });
 * Route::mount('api:v2', function () { ... });
 *
 * // 快捷宏
 * Route::api(function () { ... });
 * Route::api('v2', function () { ... });
 *
 * // 取消能力
 * Route::mount('api')->withoutAuth(function () { ... });
 *
 * // 扩展 mount
 * Route::extendMount('admin', function (string $version = 'v1') {
 *     return ['extends' => "api:{$version}", 'prefix' => '/admin'];
 * });
 *
 * // 扩展能力
 * Route::extendAbility('audit', function ($route) {
 *     return $route->middleware('audit.log');
 * });
 * ```
 */
class MountManager
{
    /**
     * 已注册的 mount 定义
     *
     * @var array<string, callable>
     */
    protected array $mounts = [];

    /**
     * 已注册的能力定义
     *
     * @var array<string, callable>
     */
    protected array $abilities = [];

    /**
     * 已注册的快捷宏
     *
     * @var array<string, true>
     */
    protected array $shortcuts = [];

    /**
     * 注册一个 mount 定义
     *
     * @param string   $name   mount 名称
     * @param callable $resolver 接收参数数组，返回配置数组的闭包
     *                          配置数组支持：extends, prefix, abilities, instance
     * @return $this
     */
    public function extend(string $name, callable $resolver): static
    {
        $this->mounts[$name] = $resolver;

        // 自动注册快捷宏
        $this->registerShortcut($name);

        return $this;
    }

    /**
     * 注册一个能力定义
     *
     * 能力是接收 RouteRegistrar 并返回 RouteRegistrar 的管道函数。
     *
     * @param string   $name   能力名称
     * @param callable $handler 接收 RouteRegistrar，返回 RouteRegistrar
     *                          function(RouteRegistrar $route): RouteRegistrar
     * @return $this
     */
    public function extendAbility(string $name, callable $handler): static
    {
        $this->abilities[$name] = $handler;

        return $this;
    }

    /**
     * 检查能力是否已注册
     *
     * @param string $name 能力名称
     * @return bool
     */
    public function hasAbility(string $name): bool
    {
        return isset($this->abilities[$name]);
    }

    /**
     * 解析并执行一个 mount
     *
     * 回调接收 MountInstance 作为 $route 参数，支持链式调用：
     * ```php
     * Route::mount('api', function ($route) {
     *     $route->get('/users', [UserController::class, 'index']);
     *     $route->withoutAudit(function ($route) {
     *         $route->get('/no-audit', [SomeController::class, 'index']);
     *     });
     * });
     * ```
     *
     * @param string   $spec     mount 规格，格式："{name}:{param1},{param2},..."
     * @param callable $callback 路由定义闭包，接收 MountInstance 作为 $route 参数
     * @return void
     */
    public function mount(string $spec, callable $callback): void
    {
        // 创建 MountInstance 并执行
        $instance = $this->instance($spec);
        $instance->execute($callback);
    }

    /**
     * 创建一个挂载实例，支持链式取消能力
     *
     * @param string $spec mount 规格
     * @return MountInstance
     */
    public function instance(string $spec): MountInstance
    {
        return new MountInstance($this, $spec);
    }

    /**
     * 解析 mount 规格
     *
     * 格式："{name}:{param1},{param2},..."
     * 示例：
     * - "api"        → name=api, params=[]
     * - "api:v2"     → name=api, params=['v2']
     * - "org:acme-corp,v2" → name=org, params=['acme-corp', 'v2']
     *
     * @param string $spec
     * @return array [name, params]
     */
    protected function parseSpec(string $spec): array
    {
        $parts = explode(':', $spec, 2);
        $name = $parts[0];
        $params = [];

        if (isset($parts[1]) && $parts[1] !== '') {
            $params = explode(',', $parts[1]);
        }

        return [$name, $params];
    }

    /**
     * 递归解析 mount 配置
     *
     * @param string $name   mount 名称
     * @param array  $params 参数列表
     * @return array 解析后的配置
     */
    public function resolveMount(string $name, array $params = []): array
    {
        if (! isset($this->mounts[$name])) {
            throw new \InvalidArgumentException("Mount [{$name}] is not defined.");
        }

        // 调用解析器获取配置
        $config = call_user_func_array($this->mounts[$name], $params);

        // 处理继承
        if (isset($config['extends'])) {
            $extends = (array) $config['extends']; // 支持字符串和数组
            $merged = [
                'prefix' => '',
                'abilities' => [],
                'without' => [],
            ];

            foreach ($extends as $extendSpec) {
                [$extendName, $extendParams] = $this->parseSpec($extendSpec);
                $parentConfig = $this->resolveMount($extendName, $extendParams);

                // 合并前缀
                $parentPrefix = $parentConfig['prefix'] ?? '';
                $merged['prefix'] = $merged['prefix']
                    ? $this->mergePrefix($merged['prefix'], $parentPrefix)
                    : $parentPrefix;

                // 合并能力
                $merged['abilities'] = array_merge(
                    $merged['abilities'],
                    $parentConfig['abilities'] ?? []
                );

                // 合并 without
                $merged['without'] = array_merge(
                    $merged['without'],
                    $parentConfig['without'] ?? []
                );
            }

            // 当前配置覆盖父级
            // 前缀合并规则：
            // - 绝对路径（以 / 开头）→ 直接替换父级前缀
            // - 相对路径（不以 / 开头）→ 追加到父级后面
            if (isset($config['prefix'])) {
                $merged['prefix'] = $this->mergePrefix($merged['prefix'], $config['prefix']);
            }

            if (isset($config['abilities'])) {
                $merged['abilities'] = array_merge(
                    $merged['abilities'],
                    $config['abilities']
                );
            }

            if (isset($config['without'])) {
                $merged['without'] = array_merge($merged['without'], $config['without']);
            }

            // 保留 instance
            if (isset($config['instance'])) {
                $merged['instance'] = $config['instance'];
            }

            return $merged;
        }

        return $config;
    }

    /**
     * 应用能力到路由注册器
     *
     * @param RouteRegistrar $route
     * @param string         $ability 能力名称
     * @return RouteRegistrar
     */
    public function applyAbility(RouteRegistrar $route, string $ability): RouteRegistrar
    {
        if (! isset($this->abilities[$ability])) {
            throw new \InvalidArgumentException("Ability [{$ability}] is not defined.");
        }

        $handler = $this->abilities[$ability];

        return $handler($route);
    }

    /**
     * 合并两个前缀
     *
     * 支持绝对路径和相对路径两种模式：
     * - 绝对路径（以 / 开头）→ 直接替换父级前缀
     * - 相对路径（不以 / 开头）→ 追加到父级后面
     *
     * @param string $base   基础前缀
     * @param string $append 追加的前缀
     * @return string
     */
    protected function mergePrefix(string $base, string $append): string
    {
        // 绝对路径，直接替换父级
        if ($this->isAbsolutePrefix($append)) {
            return $append;
        }

        return rtrim($base, '/') . '/' . ltrim($append, '/');
    }

    /**
     * 判断是否为绝对路径前缀
     *
     * 绝对路径以 / 开头，相对路径不以 / 开头。
     *
     * @param string $prefix
     * @return bool
     */
    protected function isAbsolutePrefix(string $prefix): bool
    {
        return str_starts_with($prefix, '/');
    }

    /**
     * 为 mount 注册快捷宏
     *
     * @param string $name mount 名称
     */
    protected function registerShortcut(string $name): void
    {
        // 避免重复注册
        if (isset($this->shortcuts[$name])) {
            return;
        }

        $this->shortcuts[$name] = true;

        // 注册快捷宏到 Route facade
        // 使用 $this 引用当前 MountManager 实例，确保宏能访问已注册的 mount
        $manager = $this;
        Route::macro($name, function (...$args) use ($name, $manager) {
            // 解析参数：可能是 (callback) 或 (params..., callback)
            $callback = null;
            $params = [];

            foreach ($args as $arg) {
                if (is_callable($arg)) {
                    $callback = $arg;
                } else {
                    $params[] = (string) $arg;
                }
            }

            if ($callback === null) {
                // 没有 callback，返回 MountInstance 支持链式调用
                $spec = $name . (empty($params) ? '' : ':' . implode(',', $params));

                return $manager->instance($spec);
            }

            // 构建 spec
            $spec = $name . (empty($params) ? '' : ':' . implode(',', $params));
            $manager->mount($spec, $callback);
        });
    }
}
