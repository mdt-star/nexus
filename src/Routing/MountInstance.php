<?php

namespace MdtStar\Nexus\Routing;

use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/**
 * 路由挂载实例
 *
 * 代理 RouteRegistrar，自动应用 mount 配置中的 prefix 和 middlewares。
 *
 * 使用示例：
 * ```php
 * Route::mount('api', function ($route) {
 *     $route->get('/users', [UserController::class, 'index']);
 * });
 *
 * Route::mount('api')->get('/users', [UserController::class, 'index']);
 * ```
 */
class MountInstance
{
    protected MountManager $manager;

    protected string $spec;

    protected ?RouteRegistrar $route = null;

    /** @var array<string, mixed>|null 缓存解析后的 defaults */
    protected ?array $defaults = null;

    public function __construct(MountManager $manager, string $spec)
    {
        $this->manager = $manager;
        $this->spec = $spec;
    }

    /**
     * 动态方法调用，转交给底层的 RouteRegistrar
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->resolver()->$name(...$arguments);
    }

    /**
     * 执行路由定义
     *
     * 使用 Route::group 一次性注入 prefix、middleware、defaults 到 groupStack，
     * 子路由通过 RouteRegistrar 注册时会自动合并 groupStack 属性。
     *
     * 注意：进入回调前重置 RouteRegistrar 的 prefix 为空，
     * 避免 RouteRegistrar::compileAction 中的 prefix 与 groupStack 中的 prefix 翻倍。
     */
    public function execute(callable $callback): void
    {
        [$name, $params] = $this->parseSpec($this->spec);
        $config = $this->manager->resolveMount($name, $params);

        $attributes = [];

        if (! empty($config['prefix'])) {
            $attributes['prefix'] = $config['prefix'];
        }
        if (! empty($config['middlewares'])) {
            $attributes['middleware'] = array_unique($config['middlewares']);
        }
        if (! empty($config['defaults'])) {
            $attributes['defaults'] = $config['defaults'];
        }

        Route::group($attributes, function () use ($callback) {
            // 重置 RouteRegistrar 的 prefix 为空字符串，
            // prefix 已由 Route::group 注入到 groupStack，不需要 RouteRegistrar 重复设置
            $this->route = Route::prefix('');

            $callback($this);
        });
    }

    /**
     * 解析并缓存 mount 的 defaults 配置
     *
     * @return array<string, mixed>
     */
    protected function resolveDefaults(): array
    {
        if ($this->defaults === null) {
            [$name, $params] = $this->parseSpec($this->spec);
            $config = $this->manager->resolveMount($name, $params);
            $this->defaults = $config['defaults'] ?? [];
        }

        return $this->defaults;
    }

    /**
     * 获取或创建底层的 RouteRegistrar
     *
     * 在 RouteRegistrar 上设置 prefix、defaults 和 middleware，
     * 通过单层 group 统一注入给所有子路由。
     *
     * defaults 属性 RouteRegistrar 原生不支持，
     * 通过 Route::macro 注册 'defaults' 来实现。
     */
    protected function resolver(): RouteRegistrar
    {
        if ($this->route === null) {
            [$name, $params] = $this->parseSpec($this->spec);
            $config = $this->manager->resolveMount($name, $params);

            $this->route = Route::prefix($config['prefix'] ?? '');

            if (! empty($config['middlewares'])) {
                $this->route->middleware(array_unique($config['middlewares']));
            }

            if (! empty($config['defaults'])) {
                $this->route->defaults($config['defaults']);
            }
        }

        return $this->route;
    }

    /**
     * 解析 mount 规格
     *
     * 格式："{name}:{param1},{param2},..."
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
}
