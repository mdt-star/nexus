<?php

namespace MdtStar\Nexus\Providers;

use MdtStar\Nexus\Console\SyncPermissionsCommand;
use MdtStar\Nexus\Http\Middlewares\VerifyAuthTagMiddleware;
use MdtStar\Nexus\Jobs\SyncPlatformPackagesJob;
use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permission;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Observers\PermissionObserver;
use MdtStar\Nexus\Routing\MountManager;
use MdtStar\Nexus\Services\DynamicConfigManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Nexus 核心服务提供者
 *
 * 负责：
 * - 注册模型观察者
 * - 注入全局 DataScope
 * - 加载动态配置
 * - 发布配置文件和语言文件
 * - 将本包 User 模型注入为 Laravel 默认认证用户模型
 * - 自动检测包变更并分发同步任务
 * - 注册 Builder Macro（setSubject/getSubject/hasSubject）
 * - 注册 Route::auth() 和 Route::tag() 路由宏
 */
class NexusServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/nexus.php',
            'nexus'
        );

        // 注册动态配置管理器为单例
        $this->app->singleton(DynamicConfigManager::class, function ($app) {
            return new DynamicConfigManager();
        });

        // 注册路由挂载管理器为单例
        $this->app->singleton(MountManager::class, function ($app) {
            return new MountManager();
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 注册中间件别名
        $this->registerMiddleware();

        // 注册路由宏
        $this->registerRouteMacros();

        // 注册默认挂载点和能力
        $this->registerDefaultMounts();

        // 注册 Builder Macro（HasDataScope 的 withSubject 依赖此 Macro）
        $this->registerBuilderMacros();

        // 注册模型观察者
        Permission::observe(PermissionObserver::class);

        // 将本包 User 模型注入为 Laravel 默认认证用户模型
        $this->registerUserProvider();

        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../../config/nexus.php' => config_path('nexus.php'),
        ], 'nexus-config');

        // 发布语言文件
        $this->publishes([
            __DIR__ . '/../../lang' => $this->app->langPath('vendor/nexus'),
        ], 'nexus-lang');

        // 加载路由
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // 加载迁移
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // 加载翻译
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'nexus');

        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPermissionsCommand::class,
            ]);
        }

        // 注入全局 DataScope
        $this->registerGlobalDataScope();

        // 自动检测包变更，分发同步任务
        $this->detectPackageChanges();

        // 将数据库持久配置合并到 Laravel Config Repository
        // 使 config('nexus.*') 能读到数据库覆盖值
        $this->mergePersistentConfig();
    }

    /**
     * 注册中间件别名
     *
     * auth.tag — 权限校验中间件，继承 Authenticate 自动要求登录
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('auth.tag', VerifyAuthTagMiddleware::class);
    }

    /**
     * 注册路由宏
     *
     * Route::auth() — 自动注入 package_id 到 defaults，支持自动推断和显式指定包名
     * Route::tag() — 将自定义 tag 写入 defaults('auth_tag')
     * Route::mount() — 路由挂载系统
     * Route::extendMount() — 扩展路由挂载
     * Route::extendAbility() — 扩展能力
     */
    protected function registerRouteMacros(): void
    {
        /**
         * Route::auth() — 认证路由组
         *
         * 自动注入 package_id 和 package_name 到路由 defaults，
         * 使 VerifyAuthTagMiddleware 能精确查询权限。
         *
         * 用法：
         * ```php
         * // 自动推断包名（推荐，在模块 ServiceProvider 中使用）
         * Route::auth(function () {
         *     Route::get('/articles', [ArticleController::class, 'index']);
         * });
         *
         * // 显式指定包名
         * Route::auth('third-party/module-article', function () {
         *     Route::get('/articles', [ArticleController::class, 'index']);
         * });
         * ```
         */
        Route::macro('auth', function (string $packageName = null, callable $callback = null) {
            // 兼容两种调用方式
            if ($callback === null) {
                $callback = $packageName;
                $packageName = null;
            }

            // 没有显式传入时，尝试自动推断
            if (! $packageName) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
                $callerFile = $trace[0]['file'] ?? '';
                if (preg_match('#/vendor/([^/]+/[^/]+)/#', $callerFile, $matches)) {
                    $packageName = $matches[1];
                }
            }

            $packageId = $packageName ? Package::idByName($packageName) : null;

            return Route::group([
                'middleware' => ['auth.tag'],
                'defaults' => [
                    'package_id' => $packageId,
                    'package_name' => $packageName,
                ],
            ], $callback);
        });

        /**
         * Route::tag() — 设置自定义权限标识
         *
         * 将 tag 写入路由的 defaults('auth_tag')，
         * VerifyAuthTagMiddleware 会优先使用此值。
         *
         * 用法：
         * ```php
         * Route::auth(function () {
         *     Route::get('/custom', [CustomController::class, 'index'])->tag('custom:tag');
         * });
         * ```
         */
        Route::macro('tag', function (string $tag) {
            /** @var \Illuminate\Routing\Route $this */
            return $this->setDefaults(array_merge(
                $this->defaults,
                ['auth_tag' => $tag]
            ));
        });

        // ============================================================
        // Route Mount 系统
        // ============================================================

        /**
         * Route::mount() — 路由挂载
         *
         * 使用预定义或自定义的 mount 来注册路由组。
         *
         * 用法：
         * ```php
         * Route::mount('api', function () {
         *     Route::get('/articles', [ArticleController::class, 'index']);
         *     // → /api/v1/articles + auth
         * });
         *
         * Route::mount('api:v2', function () {
         *     // → /api/v2/articles + auth
         * });
         * ```
         */
        Route::macro('mount', function (string $spec, callable $callback) {
            /** @var MountManager $manager */
            $manager = app(MountManager::class);
            $manager->mount($spec, $callback);
        });

        /**
         * Route::extendMount() — 扩展路由挂载
         *
         * 注册一个新的 mount 定义。
         *
         * 用法：
         * ```php
         * Route::extendMount('admin', function (string $version = 'v1') {
         *     return [
         *         'extends' => "api:{$version}",
         *         'prefix' => '/admin',
         *     ];
         * });
         * ```
         */
        Route::macro('extendMount', function (string $name, callable $resolver) {
            /** @var MountManager $manager */
            $manager = app(MountManager::class);
            $manager->extend($name, $resolver);
        });

        // extendAbility 宏已移除，改用 middlewares 声明式配置
    }

    /**
     * 注册默认挂载点
     *
     * 预定义：
     * - auth mount：中间件 [auth.tag]，defaults 自动注入 package_id/package_name
     * - api mount：继承 auth 域，前缀 /api/{version}，追加中间件 [api]
     * - admin mount：继承 api 域，追加前缀 /admin
     *
     * 继承链：admin → api → auth
     * 所有通过 Route::admin() 注册的路由自动获得 auth.tag 中间件和 package_id/package_name
     */
    protected function registerDefaultMounts(): void
    {
        /** @var MountManager $manager */
        $manager = $this->app->make(MountManager::class);

        // 注册 auth mount（基础认证域）
        // 自动注入 package_id 和 package_name 到路由 defaults，
        // 使 VerifyAuthTagMiddleware 能精确查询权限。
        $manager->extend('auth', function () {
            // 通过 debug_backtrace 自动推断调用者包名
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
            $packageName = null;
            foreach ($trace as $frame) {
                $file = $frame['file'] ?? '';
                if (preg_match('#/vendor/([^/]+/[^/]+)/#', $file, $matches)) {
                    $packageName = $matches[1];
                    break;
                }
            }

            $packageId = $packageName ? Package::idByName($packageName) : null;

            return [
                'middlewares' => ['auth.tag'],
                'defaults' => [
                    'package_id' => $packageId,
                    'package_name' => $packageName,
                ],
            ];
        });

        // 注册 api mount（继承 auth 域）
        $manager->extend('api', function (string $version = 'v1') {
            return [
                'extends' => 'auth',
                'prefix' => "/api/{$version}",
                'middlewares' => ['api'],
            ];
        });

        // 注册 admin mount（继承 api 域）
        // 使用相对路径「admin」（不以 / 开头），以追加到 api 前缀之后
        $manager->extend('admin', function (string $version = 'v1') {
            return [
                'extends' => "api:{$version}",
                'prefix' => 'admin',
            ];
        });
    }

    /**
     * 将数据库持久配置合并到 Laravel Config Repository
     *
     * 在 boot() 末尾调用，此时数据库迁移已执行、Config 模型可用。
     * 数据库配置覆盖静态配置（同名 key 以数据库为准），
     * 此后 config('nexus.*') 返回的是合并后的值。
     *
     * 安全防护：
     * - 检查 configs 表是否存在，规避迁移未执行的情况
     * - 仅在非 console 环境执行（artisan 命令中不需要合并）
     */
    protected function mergePersistentConfig(): void
    {
        // 在 console 环境中跳过（如 migrate、config:cache 等命令）
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('configs')) {
                return;
            }

            $manager = $this->app->make(DynamicConfigManager::class);
            $manager->mergeIntoConfig();
        } catch (\Throwable) {
            // 静默失败：迁移未执行或数据库不可用时跳过
        }
    }


    /**
     * 注册 Builder Macro
     *
     * 为 Eloquent Builder 注册 setSubject/getSubject/hasSubject 方法，
     * 用于 HasDataScope Trait 的 withSubject() 本地作用域，
     * 实现将作用主体绑定到 Builder 变量上（作用在变量上，用完即弃）。
     */
    protected function registerBuilderMacros(): void
    {
        Builder::macro('setSubject', function ($subject) {
            $this->subject = $subject;

            return $this;
        });

        Builder::macro('getSubject', function () {
            return $this->subject ?? null;
        });

        Builder::macro('hasSubject', function () {
            return isset($this->subject);
        });
    }

    /**
     * 将本包 User 模型注入为 Laravel 默认认证用户模型
     *
     * 通过 Auth::provider() 注册自定义用户提供器，
     * 使用本包 MdtStar\Nexus\Models\User 作为用户模型。
     * 此后 Auth::user() 返回的即为此 User 实例。
     */
    protected function registerUserProvider(): void
    {
        $this->app['auth']->provider('nexus-user', function ($app, array $config) {
            return $app->make(\Illuminate\Auth\EloquentUserProvider::class, [
                'hasher' => $app['hash'],
                'model' => User::class,
            ]);
        });

        // 将 web guard 的用户提供器替换为本包实现
        // 外部项目可通过 config/auth.php 的 providers 配置使用 nexus-user
        $this->app->make('config')->set('auth.providers.users', [
            'driver' => 'nexus-user',
            'model' => User::class,
        ]);
    }

    /**
     * 注入全局数据范围
     */
    protected function registerGlobalDataScope(): void
    {
        if (! config('nexus.data_scope.enabled', true)) {
            return;
        }

        // 通过 Eloquent 的全局 Scope 机制注入
        // 具体实现由 HasDataScope Trait 在模型层完成
    }

    /**
     * 自动检测包变更
     *
     * 通过监听 bootstrap/cache/packages.php（生产环境）或
     * vendor/composer/installed.json（开发环境）的 mtime 变化，
     * 判断是否有新包安装，有则分发队列任务同步权限和菜单。
     *
     * 在应用完全启动后（booted）执行，避免阻塞当前请求。
     */
    protected function detectPackageChanges(): void
    {
        $this->app->booted(function () {
            // 优先用 packages.php（生产环境，artisan optimize 后生成）
            $cacheFile = base_path('bootstrap/cache/packages.php');

            // 开发环境用 vendor/composer/installed.json
            if (! file_exists($cacheFile)) {
                $cacheFile = base_path('vendor/composer/installed.json');
            }

            if (! file_exists($cacheFile)) {
                return;
            }

            $currentMtime = filemtime($cacheFile);
            $lastMtime = cache()->get('platform_packages_mtime', 0);

            if ($currentMtime > $lastMtime) {
                cache()->forever('platform_packages_mtime', $currentMtime);
                SyncPlatformPackagesJob::dispatch();
            }
        });
    }
}
