<?php

namespace MdtStar\Nexus\Routing;

use Illuminate\Support\Facades\Route;

/**
 * 路由挂载管理器（Mount Manager）
 *
 * 管理路由域（Mount）的注册、继承和解析。
 *
 * 核心概念：
 * - Mount = 前缀 + 一组中间件（middlewares）
 * - 基础包预定义 api mount
 * - 开发者通过 extend() 注册自己的 mount
 * - mount 可以继承一个或多个 mount
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
 * // 扩展 mount
 * Route::extendMount('admin', function (string $version = 'v1') {
 *     return ['extends' => "api:{$version}", 'prefix' => '/admin'];
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
     * 已注册的快捷宏
     *
     * @var array<string, true>
     */
    protected array $shortcuts = [];

    /**
     * 注册一个 mount 定义
     *
     * @param string   $name     mount 名称
     * @param callable $resolver 接收参数数组，返回配置数组的闭包
     *                           配置数组支持：extends, prefix, middlewares
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
     * 解析并执行一个 mount
     *
     * 回调接收 MountInstance 作为 $route 参数。
     *
     * @param string   $spec     mount 规格，格式："{name}:{param1},{param2},..."
     * @param callable $callback 路由定义闭包，接收 MountInstance 作为 $route 参数
     * @return void
     */
    public function mount(string $spec, callable $callback): void
    {
        $instance = $this->instance($spec);
        $instance->execute($callback);
    }

    /**
     * 创建一个挂载实例
     *
     * @param string $spec mount 规格
     * @return MountInstance
     */
    public function instance(string $spec): MountInstance
    {
        return new MountInstance($this, $spec);
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
            $extends = (array) $config['extends'];
            $merged = [
                'prefix' => '',
                'middlewares' => [],
            ];

            foreach ($extends as $extendSpec) {
                [$extendName, $extendParams] = $this->parseSpec($extendSpec);
                $parentConfig = $this->resolveMount($extendName, $extendParams);

                // 合并前缀
                $parentPrefix = $parentConfig['prefix'] ?? '';
                $merged['prefix'] = $merged['prefix']
                    ? $this->mergePrefix($merged['prefix'], $parentPrefix)
                    : $parentPrefix;

                // 合并中间件
                $merged['middlewares'] = array_merge(
                    $merged['middlewares'],
                    $parentConfig['middlewares'] ?? []
                );
            }

            // 当前配置覆盖父级
            if (isset($config['prefix'])) {
                $merged['prefix'] = $this->mergePrefix($merged['prefix'], $config['prefix']);
            }

            if (isset($config['middlewares'])) {
                $merged['middlewares'] = array_merge(
                    $merged['middlewares'],
                    $config['middlewares']
                );
            }

            return $merged;
        }

        return $config;
    }

    /**
     * 合并两个前缀
     *
     * - 绝对路径（以 / 开头）→ 直接替换父级前缀
     * - 相对路径（不以 / 开头）→ 追加到父级后面
     */
    protected function mergePrefix(string $base, string $append): string
    {
        if ($this->isAbsolutePrefix($append)) {
            return $append;
        }

        return rtrim($base, '/') . '/' . ltrim($append, '/');
    }

    /**
     * 判断是否为绝对路径前缀
     */
    protected function isAbsolutePrefix(string $prefix): bool
    {
        return str_starts_with($prefix, '/');
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

    /**
     * 为 mount 注册快捷宏
     */
    protected function registerShortcut(string $name): void
    {
        if (isset($this->shortcuts[$name])) {
            return;
        }

        $this->shortcuts[$name] = true;

        $manager = $this;
        Route::macro($name, function (...$args) use ($name, $manager) {
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
                $spec = $name . (empty($params) ? '' : ':' . implode(',', $params));
                return $manager->instance($spec);
            }

            $spec = $name . (empty($params) ? '' : ':' . implode(',', $params));
            $manager->mount($spec, $callback);
        });
    }
}
