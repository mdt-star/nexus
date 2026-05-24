<?php

namespace MdtStar\Nexus\Routing;

use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;

/**
 * 路由挂载实例
 *
 * 支持链式调用取消能力，例如：
 * ```php
 * Route::mount('api', function ($route) {
 *     $route->get('/users', [UserController::class, 'index']);
 * });
 *
 * Route::mount('api')->withoutAuth(function ($route) {
 *     $route->get('/public', [PublicController::class, 'index']);
 * });
 *
 * Route::mount('api', function ($route) {
 *     $route->get('/users', [UserController::class, 'index']);
 *     $route->withoutAudit(function ($route) {
 *         $route->get('/no-audit', [SomeController::class, 'index']);
 *     });
 * });
 * ```
 *
 * 通过 __call 魔术方法动态处理 without{Ability}() 调用，
 * 如果能力未注册则转交给 RouteRegistrar，让用户扩展的 macro 有机会处理。
 */
class MountInstance
{
    /**
     * 挂载管理器
     */
    protected MountManager $manager;

    /**
     * mount 规格
     */
    protected string $spec;

    /**
     * 要取消的能力列表
     *
     * @var array<string, true>
     */
    protected array $without = [];

    /**
     * 底层的 RouteRegistrar 实例（懒加载）
     */
    protected ?RouteRegistrar $route = null;

    /**
     * @param MountManager $manager
     * @param string       $spec    mount 规格
     */
    public function __construct(MountManager $manager, string $spec)
    {
        $this->manager = $manager;
        $this->spec = $spec;
    }

    /**
     * 动态方法调用
     *
     * 匹配规则：
     * - 方法名以 "without" 开头 → 提取能力名称
     *   - 能力已注册 → 加入取消列表，有回调则执行
     *   - 能力未注册 → 转交给 RouteRegistrar（让用户扩展的 macro 有机会处理）
     * - 其他方法 → 转交给 RouteRegistrar
     *
     * @param string $name      方法名
     * @param array  $arguments 参数
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (str_starts_with($name, 'without')) {
            $ability = lcfirst(substr($name, 7));

            if ($this->manager->hasAbility($ability)) {
                // 能力已注册 → 加入取消列表
                $this->without[$ability] = true;
                $this->route = null; // 重置路由，重新应用能力

                // 有回调则执行
                if (isset($arguments[0]) && is_callable($arguments[0])) {
                    $this->execute($arguments[0]);
                }

                return $this;
            }

            // 能力未注册 → 转交给 RouteRegistrar，让用户扩展的 macro 有机会处理
            return $this->resolver()->$name(...$arguments);
        }

        // 非 without 方法，转交给 RouteRegistrar
        return $this->resolver()->$name(...$arguments);
    }

    /**
     * 获取或创建底层的 RouteRegistrar
     *
     * @return RouteRegistrar
     */
    protected function resolver(): RouteRegistrar
    {
        if ($this->route === null) {
            [$name, $params] = $this->parseSpec($this->spec);
            $config = $this->manager->resolveMount($name, $params);

            // 合并 without
            $config['without'] = array_merge($config['without'] ?? [], array_keys($this->without));

            // 构建 RouteRegistrar
            $this->route = Route::prefix($config['prefix'] ?? '');

            // 应用能力（排除被取消的）
            foreach ($config['abilities'] ?? [] as $ability) {
                if (! in_array($ability, $config['without'], true)) {
                    $this->route = $this->manager->applyAbility($this->route, $ability);
                }
            }
        }

        return $this->route;
    }

    /**
     * 执行路由定义
     *
     * 解析 mount 配置，应用能力（排除被取消的），
     * 然后执行回调，传入 $this 作为 $route 参数。
     *
     * @param callable $callback 接收 $this 作为 $route 参数
     * @return void
     */
    public function execute(callable $callback): void
    {
        // 执行路由定义，传入 $this 作为 $route 参数
        $this->resolver()->group(function () use ($callback) {
            $callback($this);
        });
    }

    /**
     * 解析 mount 规格
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
}
