<?php

namespace MdtStar\Nexus\Tests\Feature;

use MdtStar\Nexus\Exceptions\PermissionDeniedException;
use MdtStar\Nexus\Models\Package;
use MdtStar\Nexus\Models\Permissionable;
use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/**
 * VerifyAuthTagMiddleware 权限标签检查集成测试
 *
 * 覆盖：
 * - 未登录访问受保护路由 → 401
 * - 超级管理员跳过权限检查
 * - 有权限的用户可以访问
 * - 无权限的用户被拒绝
 * - 中间件参数指定 tag
 * - Route::tag() 自定义 tag
 * - 无 tag 可推断时抛 tag_not_found
 * - 用户未实现 HasPermission 接口
 * - package_id 精确匹配
 * - 全局 tag（package_id IS NULL）匹配
 */
class AuthTagMiddlewareTest extends TestCase
{
    protected User $user;
    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->package = Package::create(['name' => 'test/article']);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function 未登录访问受保护路由返回401()
    {
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(401);
    }

    /** @test */
    public function 超级管理员跳过权限检查()
    {
        Config::set('nexus.super_admin_id', $this->user->id);
        $this->actingAs($this->user);

        // 不授予任何 tag，超级管理员应能通过
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function 有权限的用户可以访问()
    {
        // 授予全局 desktop:list tag（package_id = null）
        // Route::admin() 注册的路由没有 package_id defaults
        // 所以 hasTag 会传 package_id=null，需要匹配 package_id IS NULL 的记录
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        // GET /api/v1/admin/desktops → DesktopController@index → 自动推断 tag=desktop:list
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function 无权限的用户被拒绝()
    {
        // 不授予任何 tag
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing desktop:list permission']);
    }

    /** @test */
    public function 中间件参数指定tag有权限可通过()
    {
        // 注册一条测试路由，显式指定 tag
        Route::get('/api/test-tag-param', function () {
            return 'ok';
        })->middleware('auth.tag:custom:tag');

        // 授予 custom:tag
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'custom:tag',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-param');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function 中间件参数指定tag无权限被拒绝()
    {
        Route::get('/api/test-tag-param-deny', function () {
            return 'ok';
        })->middleware('auth.tag:custom:tag');

        // 不授予 custom:tag
        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-param-deny');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing custom:tag permission']);
    }

    /** @test */
    public function route_tag自定义tag有权限可通过()
    {
        // Route::tag() 是 Route facade 的宏，不能在 Route 实例上调用
        // 改用 defaults() 设置 auth_tag
        Route::get('/api/test-tag-method', function () {
            return 'ok';
        })->middleware('auth.tag')->setDefaults(['auth_tag' => 'article:list']);

        // 授予 article:list
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'article:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-method');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function route_tag自定义tag无权限被拒绝()
    {
        Route::get('/api/test-tag-method-deny', function () {
            return 'ok';
        })->middleware('auth.tag')->setDefaults(['auth_tag' => 'article:list']);

        // 不授予 article:list
        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-tag-method-deny');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing article:list permission']);
    }

    /** @test */
    public function 无tag可推断时抛tag_not_found()
    {
        // 闭包路由，无控制器可推断，无 defaults，中间件有显式 guard 参数
        // 使用 auth.tag:web（有显式参数）确保中间件主动检查而非静默通过
        // web guard 与 actingAs() 默认的 guard 一致，可正常通过认证检查
        Route::get('/api/test-no-tag', function () {
            return 'ok';
        })->middleware('auth.tag:web');

        $this->actingAs($this->user);

        $response = $this->getJson('/api/test-no-tag');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Unable to determine permission tag']);
    }

    /** @test */
    public function 用户未实现HasPermission接口被拒绝()
    {
        // 用普通用户（非 Nexus User）登录
        $plainUser = new \Illuminate\Foundation\Auth\User();
        $plainUser->id = 999;

        $this->actingAs($plainUser);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Subject does not implement permission interface']);
    }

    /** @test */
    public function package_id精确匹配有权限可通过()
    {
        // 授予 package1 的 desktop:list
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        // 注册一条带 package_id defaults 的路由
        Route::get('/api/test-package-match', function () {
            return 'ok';
        })->middleware('auth.tag:desktop:list')->setDefaults([
            'package_id' => $this->package->id,
        ]);

        $response = $this->getJson('/api/test-package-match');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function package_id精确匹配无权限被拒绝()
    {
        $package2 = Package::create(['name' => 'test/other']);

        // 只授予 package1 的 desktop:list
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        // 请求 package2 的 desktop:list → 不匹配
        Route::get('/api/test-package-mismatch', function () {
            return 'ok';
        })->middleware('auth.tag:desktop:list')->setDefaults([
            'package_id' => $package2->id,
        ]);

        $response = $this->getJson('/api/test-package-mismatch');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing desktop:list permission']);
    }

    /** @test */
    public function 全局tag无package_id可匹配()
    {
        // 授予全局 tag（package_id = null）
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'desktop:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        // 请求路由，路由无 package_id defaults
        // hasTag 应匹配 package_id IS NULL 的全局 tag
        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function 角色tag穿透到用户可通过权限检查()
    {
        $role = \MdtStar\Nexus\Models\Role::create(['name' => 'editor', 'slug' => 'editor']);
        $this->user->roles()->attach($role);

        // 给角色授予全局 tag（package_id = null）
        Permissionable::create([
            'model_type' => \MdtStar\Nexus\Models\Role::class,
            'model_id' => $role->id,
            'tag' => 'desktop:list',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/admin/desktops');

        $response->assertStatus(200);
    }

    /** @test */
    public function mount_defaults注入的package_id可匹配权限()
    {
        // 注册一个带 package_id defaults 的 mount
        // 模拟 auth mount 通过 defaults 注入 package_id 的场景
        Route::extendMount('test-auth', function () {
            return [
                'middlewares' => ['auth.tag'],
                'defaults' => [
                    'package_id' => $this->package->id,
                    'package_name' => 'test/article',
                ],
            ];
        });

        // 通过 mount 注册路由，路由自动获得 defaults 中的 package_id
        Route::mount('test-auth', function ($route) {
            $route->get('/test-defaults-pkg', function () {
                return 'ok';
            })->middleware('auth.tag:custom:tag');
        });

        // 授予对应 package 的 custom:tag
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'custom:tag',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/test-defaults-pkg');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function mount_defaults注入的package_id不匹配时拒绝()
    {
        $otherPackage = Package::create(['name' => 'test/other']);

        Route::extendMount('test-auth-2', function () use ($otherPackage) {
            return [
                'middlewares' => ['auth.tag'],
                'defaults' => [
                    'package_id' => $otherPackage->id,
                    'package_name' => 'test/other',
                ],
            ];
        });

        Route::mount('test-auth-2', function ($route) {
            $route->get('/test-defaults-pkg-deny', function () {
                return 'ok';
            })->middleware('auth.tag:custom:tag');
        });

        // 只授予了 test/article 包的 custom:tag
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'custom:tag',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/test-defaults-pkg-deny');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Permission denied, missing custom:tag permission']);
    }

    /** @test */
    public function mount_defaults注入的package_name可通过Package模型解析()
    {
        // 注册 mount，defaults 中只传 package_name，不传 package_id
        // VerifyAuthTagMiddleware 会从 defaults 中取 package_id
        // 这里测试 package_name 是否能正确映射到 package_id
        Route::extendMount('test-auth-3', function () {
            return [
                'middlewares' => ['auth.tag'],
                'defaults' => [
                    'package_name' => 'test/article',
                ],
            ];
        });

        Route::mount('test-auth-3', function ($route) {
            $route->get('/test-defaults-name', function () {
                return 'ok';
            })->middleware('auth.tag:custom:tag');
        });

        // 注意：VerifyAuthTagMiddleware 只读 package_id，不读 package_name
        // 所以这里需要 package_id 也能匹配上
        // 实际上 package_name 在 auth mount 中用于通过 Package::idByName() 查询 package_id
        // 这里直接验证 package_name 对应的 package_id 能匹配权限
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'custom:tag',
            'package_id' => $this->package->id,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/test-defaults-name');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }

    /** @test */
    public function mount_defaults为空时不影响权限检查()
    {
        // 注册一个没有 defaults 的 mount
        Route::extendMount('test-no-defaults', function () {
            return [
                'prefix' => '/test-no-defaults',
                'middlewares' => ['auth.tag'],
            ];
        });

        Route::mount('test-no-defaults', function ($route) {
            $route->get('/items', function () {
                return 'ok';
            })->middleware('auth.tag:custom:tag');
        });

        // 授予全局 tag（package_id = null）
        Permissionable::create([
            'model_type' => User::class,
            'model_id' => $this->user->id,
            'tag' => 'custom:tag',
            'package_id' => null,
        ]);

        $this->actingAs($this->user);

        $response = $this->getJson('/test-no-defaults/items');

        $response->assertStatus(200);
        $response->assertSee('ok');
    }
}
