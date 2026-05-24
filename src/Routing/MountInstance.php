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
     * 在 mount 的 group 内执行回调，传入 $this 作为 $route 参数。
     */
    public function execute(callable $callback): void
    {
        $this->resolver()->group(function () use ($callback) {
            $callback($this);
        });
    }

    /**
     * 获取或创建底层的 RouteRegistrar
     *
     * 应用顺序：
     * 1. prefix
     * 2. middlewares（声明式，直接合并，不存在覆盖问题）
     */
    protected function resolver(): RouteRegistrar
    {
        if ($this->route === null) {
            [$name, $params] = $this->parseSpec($this->spec);
            $config = $this->manager->resolveMount($name, $params);

            $this->route = Route::prefix($config['prefix'] ?? '');

            if (! empty($config['middlewares'])) {
                $this->route = $this->route->middleware(array_unique($config['middlewares']));
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
